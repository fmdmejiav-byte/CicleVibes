import L from 'leaflet';
import axios from 'axios';
import Alpine from 'alpinejs';

/*
 * Componente Alpine de CicleVibes para la navegación en bicicleta.
 *
 * Convierte el mapa en una experiencia tipo Waze para ciclistas:
 *  - Selección de origen (ubicación actual, mapa o búsqueda) y destino.
 *  - Cálculo de rutas alternativas con perfil de bicicleta.
 *  - Selección de alternativa y resaltado de la ruta activa.
 *  - Navegación con seguimiento GPS en tiempo real (watchPosition).
 *  - Detección de desviación y recálculo de la ruta.
 *  - Capa de ciclorutas (infraestructura ciclista de OpenStreetMap).
 *  - Capa de bicicletas disponibles.
 *
 * Stack: Leaflet (mapa) + OpenStreetMap (tiles) + Nominatim (búsqueda)
 *        + servicio de rutas para bicicleta (OSRM/GraphHopper).
 */
function CicleMap(config) {
    let map = null;
    let userMarker = null;
    let userAccuracyCircle = null;
    let originMarker = null;
    let destMarker = null;
    let clickTempMarker = null;

    let alternativeLayers = [];
    let activeRouteLayer = null;
    let activeRouteCoords = [];

    let bikeMarkersLayer = null;
    let cyclorutasLayer = null;
    let cyclorutasLoaded = false;

    let watchId = null;
    let userLocation = null;
    let trackingPaused = false;

    let debounceTimer = null;
    let clickPickMode = null;
    let deviationCounter = 0;
    let lastKnownRoutePointIndex = 0;

    const iconSize = 34;

    // Estilos por tipo de infraestructura ciclo (tipología del mapa oficial
    // de ciclorrutas de Barranquilla).
    const CICLORUTA_TYPES = {
        ciclorruta_calzada: { label: 'Ciclorruta en calzada', color: '#dc2626', emoji: '🔴' },
        ciclorruta_anden: { label: 'Ciclorruta en andén', color: '#ea580c', emoji: '🟠' },
        ciclobanda: { label: 'Ciclobanda', color: '#ca8a04', emoji: '🟡' },
        carril_ciclo_preferente: { label: 'Carril ciclo preferente', color: '#2563eb', emoji: '🔵' },
    };

    // Rutas GPS normales: solo recalculamos si el usuario lleva varios
    // segundos fuera de la ruta (evita peticiones innecesarias).
    const deviationThresholdMeters = 45;
    const deviationCounterThreshold = 3;

    function markerIcon(variant, glyph, extraClass = '') {
        const html = `
            <div class="cicle-marker cicle-marker--${variant} ${extraClass}" style="width:${iconSize}px;height:${iconSize}px;font-size:16px;">
                <span class="cicle-marker-pulse"></span>
                <span style="position:relative;">${glyph}</span>
            </div>`;

        return L.divIcon({
            className: '',
            html,
            iconSize: [iconSize, iconSize],
            iconAnchor: [iconSize / 2, iconSize / 2],
            popupAnchor: [0, -iconSize / 2],
        });
    }

    // ------------------------- Inicialización -------------------------
    function init() {
        // Idempotente: si el mapa ya existe (p. ej. Alpine vuelve a invocar
        // init()), reutilizamos la instancia en lugar de crear otra sobre el
        // mismo contenedor, que lanzaría "Map container is already initialized".
        if (map) return payload();

        const container = document.getElementById('cicle-map');
        if (!container) return payload();

        map = L.map(container, {
            center: [config.default_lat, config.default_lng],
            zoom: config.default_zoom,
            scrollWheelZoom: true,
            zoomControl: false,
        });

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        L.tileLayer(config.tiles_url, {
            maxZoom: config.tiles_max_zoom,
            attribution: config.tiles_attribution,
        }).addTo(map);

        map.on('click', onMapClick);
        map.on('moveend', throttledCyclorutasFetch);

        loadBicicletas();

        return payload();
    }

    // ------------------------- Búsqueda (Nominatim) -------------------------
    function triggerSearch() {
        const app = payload();
        const q = (app.query || '').trim();
        if (!q) {
            app.results = [];
            return;
        }

        app.searching = true;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            axios
                .get('/api/maps/search', { params: { q } })
                .then((res) => {
                    if (payload().query.trim() !== q) return;
                    app.results = res.data.results || [];
                })
                .catch(() => {
                    app.status = 'No pudimos completar la búsqueda. Inténtalo de nuevo.';
                })
                .finally(() => {
                    if (payload().query.trim() === q) app.searching = false;
                });
        }, 400);
    }

    function selectResult(result) {
        const app = payload();
        app.query = result.name || result.address;
        app.results = [];
        app.searching = false;

        const latlng = [result.latitude, result.longitude];
        clickTempMarker = placeTempMarker(latlng, result.name, result.address);
        map.setView(latlng, Math.max(15, map.getZoom()));

        app.selectedPlace = {
            name: result.name,
            address: result.address,
            lat: result.latitude,
            lng: result.longitude,
        };
        app.status = '';
    }

    function placeTempMarker(latlng, title, subtitle) {
        if (clickTempMarker) clickTempMarker.remove();
        clickTempMarker = L.marker(latlng, { icon: markerIcon('point', '📍') })
            .addTo(map)
            .bindPopup(`<strong>${title || 'Punto'}</strong>${subtitle ? `<br><small>${subtitle}</small>` : ''}`)
            .openPopup();
        return clickTempMarker;
    }

    // ------------------------- Selección de origen / destino -------------------------
    /**
     * Activa el modo "mejorar punto" para elegir origen o destino
     * haciendo clic en el mapa.
     */
    function pickOnMap(mode) {
        const app = payload();
        app.mode = 'pick';
        clickPickMode = mode;
        app.pickHint = mode === 'origin' ? 'Toca el mapa para fijar el origen' : 'Toca el mapa para fijar el destino';
    }

    function cancelPick() {
        const app = payload();
        clickPickMode = null;
        app.pickHint = '';
        if (app.mode === 'pick') app.mode = 'plan';
    }

    function onMapClick(e) {
        if (!clickPickMode) return;
        const app = payload();
        const { lat, lng } = e.latlng;

        if (clickPickMode === 'origin') {
            setOrigin({ lat, lng, label: 'Punto en el mapa', name: 'Punto en el mapa', address: `${lat.toFixed(5)}, ${lng.toFixed(5)}` }, true);
            addToast('Origen fijado en el mapa', 'success');
        } else {
            setDestination({ lat, lng, label: 'Punto en el mapa', name: 'Punto en el mapa', address: `${lat.toFixed(5)}, ${lng.toFixed(5)}` }, true);
            addToast('Destino fijado en el mapa', 'success');
        }

        clickPickMode = null;
        app.pickHint = '';
        app.mode = 'plan';
    }

    /**
     * Establece el origen: desde ubicación actual, desde un lugar buscado
     * o desde un clic en el mapa.
     */
    function setOrigin(point, keepViewpoint = false) {
        const app = payload();
        app.origin = { ...point };
        if (!keepViewpoint && map.getZoom() < 14) map.setView([point.lat, point.lng], 14);

        if (originMarker) {
            originMarker.setLatLng([point.lat, point.lng]);
        } else {
            originMarker = L.marker([point.lat, point.lng], { icon: markerIcon('origin', 'A') }).addTo(map);
        }
        originMarker.bindPopup(`<strong>Origen</strong><br>${point.label || point.name || 'Punto'}`).openPopup();
    }

    function setDestination(point, keepViewpoint = false) {
        const app = payload();
        app.destination = { ...point };
        if (!keepViewpoint && map.getZoom() < 14) map.setView([point.lat, point.lng], 14);

        if (destMarker) {
            destMarker.setLatLng([point.lat, point.lng]);
        } else {
            destMarker = L.marker([point.lat, point.lng], { icon: markerIcon('dest', 'B') }).addTo(map);
        }
        destMarker.bindPopup(`<strong>Destino</strong><br>${point.label || point.name || 'Punto'}`).openPopup();
    }

    /** Origen desde la ubicación actual del usuario. */
    function setOriginFromUser() {
        const app = payload();
        if (!('geolocation' in navigator)) {
            app.status = 'Tu navegador no soporta geolocalización.';
            return;
        }
        app.locating = true;
        app.status = 'Localizando tu posición…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude, accuracy } = position.coords;
                userLocation = { lat: latitude, lng: longitude };
                drawUserMarker(latitude, longitude, accuracy);
                setOrigin({ lat: latitude, lng: longitude, label: 'Tu ubicación', name: 'Mi ubicación', address: `${latitude.toFixed(5)}, ${longitude.toFixed(5)}` });
                app.locating = false;
                app.locationSet = true;
                app.status = '';
                addToast('Origen establecido en tu ubicación', 'success');
            },
            (error) => {
                app.locating = false;
                app.status = geolocationErrorMessage(error);
                addToast(app.status, 'error');
            },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
        );
    }

    function geolocationErrorMessage(error) {
        if (error.code === error.PERMISSION_DENIED) return 'Permiso de ubicación denegado. Actívalo en tu navegador.';
        if (error.code === error.POSITION_UNAVAILABLE) return 'La ubicación no está disponible en este momento.';
        if (error.code === error.TIMEOUT) return 'Tiempo de espera agotado al obtener la ubicación.';
        return 'No pudimos obtener tu ubicación.';
    }

    function drawUserMarker(lat, lng, accuracy = 0) {
        if (userAccuracyCircle) userAccuracyCircle.remove();
        if (accuracy > 0) {
            userAccuracyCircle = L.circle([lat, lng], {
                radius: accuracy,
                className: 'leaflet-user-accuracy',
                color: '#0d9488',
                fillColor: '#0d9488',
                fillOpacity: 0.12,
                weight: 1,
            }).addTo(map);
        }

        if (userMarker) {
            userMarker.setLatLng([lat, lng]);
        } else {
            const navigating = payload().navigating ? 'navigating' : '';
            userMarker = L.marker([lat, lng], {
                icon: markerIcon('user', '🚴', navigating),
                zIndexOffset: 1000,
            }).addTo(map).bindPopup('Tu posición');
        }

        map.setView([lat, lng], Math.max(map.getZoom(), 15));
        map.closePopup();
    }

    function setAsOriginFromSearch() {
        const app = payload();
        if (app.selectedPlace) {
            setOrigin(app.selectedPlace);
            addToast('Origen definido', 'success');
        }
    }

    function setAsDestinationFromSearch() {
        const app = payload();
        if (app.selectedPlace) {
            setDestination(app.selectedPlace);
            addToast('Destino definido', 'success');
        }
    }

    function reverseOriginDestination() {
        const app = payload();
        const o = app.origin;
        const d = app.destination;
        app.origin = d;
        app.destination = o;
        if (o) setDestination(o);
        if (d) setOrigin(d);
    }

    // ------------------------- Rutas alternativas -------------------------
    function isValidPoint(p) {
        return p && Number.isFinite(Number(p.lat)) && Number.isFinite(Number(p.lng));
    }

    function calculateAlternatives() {
        const app = payload();

        if (!isValidPoint(app.origin)) {
            app.status = 'Define un origen válido (toca el mapa o busca un lugar).';
            return;
        }
        if (!isValidPoint(app.destination)) {
            app.status = 'Define un destino válido (toca el mapa o busca un lugar).';
            return;
        }

        app.routing = true;
        app.status = 'Calculando rutas para bicicleta…';
        try {
            clearRenderedRoutes();
        } catch (err) {
            console.error('[CicleVibes] Error al limpiar rutas:', err);
        }

        const requestParams = {
            origin_lat: app.origin.lat,
            origin_lng: app.origin.lng,
            dest_lat: app.destination.lat,
            dest_lng: app.destination.lng,
            count: config.alternatives_count || 3,
            priorize_ciclorutas: app.priorizarCiclorutas ? 1 : 0,
        };

        axios
            .get('/api/maps/alternatives', { params: requestParams })
            .then((res) => {
                app.alternatives = res.data.routes || [];
                app.ciclorutaConnection = Boolean(res.data.cicloruta_connection);

                if (!app.alternatives.length) {
                    app.status = 'No se encontraron rutas para bicicleta.';
                    return;
                }

                app.selectedRouteId = app.alternatives[0].id;
                renderAlternatives();
                app.status = '';
                app.mode = 'routes';
            })
            .catch((err) => {
                console.error('[CicleVibes] Falla al calcular rutas:', err?.response?.status, err?.response?.data || err);
                app.status = err.response?.data?.error || 'No pudimos calcular las rutas. Inténtalo de nuevo.';
                addToast(app.status, 'error');
            })
            .finally(() => {
                app.routing = false;
            });
    }

    function renderAlternatives() {
        const app = payload();

        clearRenderedRoutes();

        (app.alternatives || []).forEach((route) => {
            const coords = Array.isArray(route.coordinates)
                ? route.coordinates.map((c) => [c[0], c[1]])
                : [];
            const isSelected = route.id === app.selectedRouteId;

            const layer = L.polyline(coords, isSelected
                ? { color: '#0d9488', weight: 7, opacity: 0.95, lineJoin: 'round', lineCap: 'round' }
                : { color: '#94a3b8', weight: 4, opacity: 0.6, dashArray: '6 8', lineJoin: 'round', lineCap: 'round' }
            ).addTo(map);

            alternativeLayers.push(layer);

            if (isSelected) {
                activeRouteLayer = layer;
                activeRouteCoords = coords;
            }
        });

        if (activeRouteLayer) {
            const bounds = activeRouteLayer.getBounds();
            map.fitBounds(bounds, { padding: [60, 60] });
        }
    }

    function selectAlternative(id) {
        const app = payload();
        app.selectedRouteId = id;
        renderAlternatives();
    }

    function clearRenderedRoutes() {
        alternativeLayers.forEach((layer) => map.removeLayer(layer));
        alternativeLayers = [];
        activeRouteLayer = null;
        activeRouteCoords = [];
    }

    // ------------------------- Navegación (tipo Waze) -------------------------
    function startNavigation() {
        const app = payload();
        if (!app.selectedRouteId) return;
        if (!('geolocation' in navigator)) {
            app.status = 'Tu navegador no soporta geolocalización.';
            return;
        }

        app.mode = 'navigating';
        app.navigating = true;
        app.routeInfo = app.alternatives.find((r) => r.id === app.selectedRouteId) || null;
        app.currentStep = 0;
        app.deviating = false;
        app.status = 'Navegación iniciada. Síguenos en el mapa.';

        if (originMarker) map.removeLayer(originMarker);
        if (destMarker) map.removeLayer(destMarker);
        originMarker = null;
        destMarker = null;

        userLocation = app.origin
            ? { lat: app.origin.lat, lng: app.origin.lng }
            : null;

        // Seguimiento GPS continuo sin recargar la página.
        watchId = navigator.geolocation.watchPosition(
            onPositionUpdate,
            () => { app.status = 'No pudimos obtener la posición GPS.'; },
            { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 }
        );

        // Ubicación inicial (origen definido al iniciar).
        if (userLocation) {
            updateUserOnMap(userLocation.lat, userLocation.lng, 12);
        }
    }

    function onPositionUpdate(position) {
        const { latitude, longitude, accuracy, speed } = position.coords;
        userLocation = { lat: latitude, lng: longitude };
        updateUserOnMap(latitude, longitude, accuracy);
        updateNavigationStatus(latitude, longitude, speed || 0);
    }

    function updateUserOnMap(lat, lng, accuracy) {
        drawUserMarker(lat, lng, accuracy);
        map.setView([lat, lng], Math.max(map.getZoom(), 15), { animate: true });
    }

    /**
     * Actualiza la distancia/tiempo restantes y detecta desviaciones
     * respecto a la ruta activa.
     */
    function updateNavigationStatus(lat, lng, speed) {
        const app = payload();
        if (!activeRouteCoords.length) return;

        const point = { lat, lng };
        const near = nearestOnPolyline(point, activeRouteCoords);
        const remainingMeters = segmentDistance(activeRouteCoords, near.index);

        const avgSpeed = speed > 0 ? speed : 3.8; // ~14 km/h aprox si no hay dato
        const remainingMinutes = remainingMeters / (avgSpeed * 60);

        app.distanceRemainingKm = Math.round((remainingMeters / 1000) * 10) / 10;
        app.timeRemainingMin = Math.max(1, Math.round(remainingMinutes));

        updateCurrentStep(near.index);

        // Detección de desviación (evitamos recalcular a cada pequeño movimiento).
        if (remainingMeters > 30 && near.distanceMeters > deviationThresholdMeters) {
            deviationCounter++;
            if (deviationCounter >= deviationCounterThreshold) {
                app.deviating = true;
                app.status = 'Te has desviado de la ruta';
            }
        } else {
            deviationCounter = 0;
            app.deviating = false;
        }
    }

    function updateCurrentStep(routePointIndex) {
        const app = payload();
        if (!app.routeInfo || !Array.isArray(app.routeInfo.steps) || activeRouteCoords.length < 2) return;

        // Progreso aproximado a lo largo de la ruta → paso de instrucción actual.
        const progress = Math.max(0, Math.min(1, routePointIndex / (activeRouteCoords.length - 1)));
        const computed = Math.min(
            app.routeInfo.steps.length - 1,
            Math.floor(progress * app.routeInfo.steps.length)
        );

        if (computed !== app.currentStep) {
            app.currentStep = computed;
        }

        lastKnownRoutePointIndex = routePointIndex;
    }

    /** Recalcula la ruta desde la posición actual hasta el destino. */
    function recalculateRoute() {
        const app = payload();
        if (!userLocation || !app.destination) return;

        app.routing = true;
        app.status = 'Calculando nueva ruta…';

        axios
            .get('/api/maps/recalculate', {
                params: {
                    origin_lat: userLocation.lat,
                    origin_lng: userLocation.lng,
                    dest_lat: app.destination.lat,
                    dest_lng: app.destination.lng,
                },
            })
            .then((res) => {
                const route = res.data.route;
                app.routeInfo = route;
                app.alternatives = [
                    { ...route, label: 'Ruta actualizada', id: route.id || 1 },
                ];
                app.selectedRouteId = (route.id || 1);
                app.deviating = false;
                deviationCounter = 0;
                clearRenderedRoutes();
                app.mode = 'navigating';

                const coords = route.coordinates.map((c) => [c[0], c[1]]);
                activeRouteLayer = L.polyline(coords, { color: '#0d9488', weight: 7, opacity: 0.95, lineJoin: 'round', lineCap: 'round' }).addTo(map);
                alternativeLayers.push(activeRouteLayer);
                activeRouteCoords = coords;

                app.distanceRemainingKm = route.distance_km;
                app.timeRemainingMin = route.duration_min;
                app.currentStep = 0;
                app.status = 'Ruta actualizada';
                addToast('Ruta recalculada desde tu posición', 'success');
            })
            .catch((err) => {
                app.status = err.response?.data?.error || 'No pudimos recalcular la ruta.';
                addToast(app.status, 'error');
            })
            .finally(() => {
                app.routing = false;
            });
    }

    function stopNavigation() {
        const app = payload();
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        app.mode = 'plan';
        app.navigating = false;
        app.deviating = false;
        app.status = 'Navegación detenida.';
        app.distanceRemainingKm = null;
        app.timeRemainingMin = null;
        app.currentStep = 0;
        if (userMarker) map.removeLayer(userMarker);
        if (userAccuracyCircle) map.removeLayer(userAccuracyCircle);
        userMarker = null;
        userAccuracyCircle = null;
    }

    function confirmRecalculate() {
        recalculateRoute();
    }

    function dismissDeviation() {
        const app = payload();
        app.deviating = false;
        deviationCounter = 0;
    }

    // ------------------------- Geometría -------------------------
    /**
     * Distancia (metros) entre dos puntos (fórmula de Haversine).
     */
    function haversineMeters(a, b) {
        const R = 6371000;
        const dLat = (b.lat - a.lat) * Math.PI / 180;
        const dLng = (b.lng - a.lng) * Math.PI / 180;
        const la1 = a.lat * Math.PI / 180;
        const la2 = b.lat * Math.PI / 180;

        const h = Math.sin(dLat / 2) ** 2 + Math.cos(la1) * Math.cos(la2) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(h));
    }

    /**
     * Distancia acumulada (metros) desde el inicio del polyline hasta el
     * índice dado (incluye los segmentos hasta ese punto).
     */
    function segmentDistance(polyline, toIndex) {
        let total = 0;
        for (let i = 1; i < polyline.length; i++) {
            if (i > toIndex) break;
            total += haversineMeters(
                { lat: polyline[i - 1][0], lng: polyline[i - 1][1] },
                { lat: polyline[i][0], lng: polyline[i][1] }
            );
        }
        return total;
    }

    /**
     * Distancia mínima (metros) entre un punto y el polyline, aproximada
     * con proyección equirectangular local para búsquedas distinguiendo
     * la proyección lat/lng.
     */
    function nearestOnPolyline(point, polyline) {
        let best = Infinity;
        let bestProjection = 0;
        let bestIndex = 0;

        for (let i = 0; i < polyline.length - 1; i++) {
            const a = { x: polyline[i][1], y: polyline[i][0] };
            const b = { x: polyline[i + 1][1], y: polyline[i + 1][0] };
            const p = { x: point.lng, y: point.lat };

            const abx = b.x - a.x;
            const aby = b.y - a.y;
            const apx = p.x - a.x;
            const apy = p.y - a.y;
            const ab2 = abx * abx + aby * aby;
            let t = ab2 > 0 ? (apx * abx + apy * aby) / ab2 : 0;
            t = Math.max(0, Math.min(1, t));

            const cx = a.x + t * abx;
            const cy = a.y + t * aby;

            const dLat = (p.y - cy) * 111320;
            const dLng = (p.x - cx) * 111320 * Math.cos(p.y * Math.PI / 180);
            const dist = Math.sqrt(dLat * dLat + dLng * dLng);

            if (dist < best) {
                best = dist;
                bestIndex = t > 0.5 ? i + 1 : i;
                bestProjection = t;
            }
        }

        return { distanceMeters: best, index: bestIndex };
    }

    // ------------------------- Bicicletas -------------------------
    function loadBicicletas() {
        if (bikeMarkersLayer) map.removeLayer(bikeMarkersLayer);
        bikeMarkersLayer = L.layerGroup().addTo(map);

        axios
            .get('/api/maps/bicicletas')
            .then((res) => {
                const bikes = res.data.bicicletas || [];
                bikes.forEach((bike) => {
                    L.marker([bike.latitude, bike.longitude], { icon: markerIcon('bike', '🚲') })
                        .addTo(bikeMarkersLayer)
                        .bindPopup(`
                            <div style="min-width:160px;">
                                <strong style="color:#059669;">${bike.marca} ${bike.modelo}</strong><br>
                                <span style="color:#666;">${bike.tipo}</span><br>
                                <span>📍 ${bike.barrio}</span><br>
                                <span style="font-size:12px;color:#888;">Dueño: ${bike.dueno}</span>
                            </div>`);
                });
            })
            .catch(() => { /* opcional */ });
    }

    function toggleBicicletas(show) {
        if (!bikeMarkersLayer) return;
        show ? map.addLayer(bikeMarkersLayer) : map.removeLayer(bikeMarkersLayer);
    }

    // ------------------------- Ciclorutas (red de infraestructura ciclista) -------------------------
    function throttledCyclorutasFetch() {
        // La red completa se carga una sola vez; al arrastrar solo nos
        // aseguramos de que la capa siga visible.
        if (!payload().showCyclorutas || cyclorutasLoaded) return;
        fetchCyclorutas();
    }

    function fetchCyclorutas() {
        if (!payload().showCyclorutas) return;
        if (cyclorutasLoaded) {
            if (cyclorutasLayer && !map.hasLayer(cyclorutasLayer)) map.addLayer(cyclorutasLayer);
            return;
        }

        axios
            .get('/api/ciclorutas')
            .then((res) => {
                if (!payload().showCyclorutas) return;
                cyclorutasLoaded = true;
                renderCyclorutas(res.data);
            })
            .catch((err) => {
                console.error('[CicleVibes] Falla al cargar ciclorutas:', err?.response?.status, err?.response?.data || err);
            });
    }

    function cyclorutaTypeInfo(type) {
        return CICLORUTA_TYPES[type] || { label: type || 'Infraestructura ciclista', color: '#a855f7' };
    }

    function renderCyclorutas(geo) {
        if (cyclorutasLayer) map.removeLayer(cyclorutasLayer);
        cyclorutasLayer = L.layerGroup().addTo(map);

        L.geoJSON(geo, {
            style: (feature) => {
                const { color } = cyclorutaTypeInfo(feature?.properties?.type);
                const base = { color, weight: 4, opacity: 0.9, lineJoin: 'round', lineCap: 'round' };
                if (feature?.properties?.type === 'ciclobanda') {
                    base.weight = 3.5;
                }
                if (feature?.properties?.type === 'carril_ciclo_preferente') {
                    base.weight = 3;
                    base.opacity = 0.8;
                    base.dashArray = '8 6';
                }
                if (feature?.properties?.type === 'ciclorruta_anden') {
                    base.dashArray = '2 6';
                }
                return base;
            },
            onEachFeature: (feature, layer) => {
                const p = feature?.properties || {};
                const { label, emoji } = cyclorutaTypeInfo(p.type);
                const name = [emoji, label, p.name ? ` · ${p.name}` : ''].filter(Boolean).join(' ');
                if (name) layer.bindPopup(`<strong>Cicloruta</strong><br><span style="color:#666;">${name}</span>`);
            },
        }).addTo(cyclorutasLayer);
    }

    function toggleCyclorutas(show) {
        if (show) {
            fetchCyclorutas();
        } else if (cyclorutasLayer) {
            map.removeLayer(cyclorutasLayer);
        }
    }

    function togglePriorizarCiclorutas() {
        const app = payload();
        app.priorizarCiclorutas = !app.priorizarCiclorutas;
        if (app.mode === 'routes' && app.origin && app.destination) {
            calculateAlternatives();
        }
    }

    // ------------------------- Acciones generales -------------------------
    function clearRoute() {
        const app = payload();
        stopNavigation();
        clearRenderedRoutes();
        app.alternatives = [];
        app.selectedRouteId = null;
        app.routeInfo = null;
        app.ciclorutaConnection = false;
        app.origin = null;
        app.destination = null;
        app.status = '';
        app.mode = 'plan';
        if (originMarker) map.removeLayer(originMarker);
        if (destMarker) map.removeLayer(destMarker);
        if (clickTempMarker) map.removeLayer(clickTempMarker);
        if (userMarker) map.removeLayer(userMarker);
        if (userAccuracyCircle) map.removeLayer(userAccuracyCircle);
        originMarker = destMarker = clickTempMarker = userMarker = userAccuracyCircle = null;
    }

    function addToast(message, type = 'info') {
        const app = payload();
        app.toast = { message, type, id: Date.now() };
        setTimeout(() => {
            if (app.toast && app.toast.id === Date.now()) app.toast = null;
        }, 4000);
    }

    // ------------------------- Estado Alpine -------------------------
    function payload() {
        return state;
    }

    let state = {
        // Búsqueda
        query: '',
        results: [],
        searching: false,
        selectedPlace: null,
        pickHint: '',

        // Panel
        panelCollapsed: false,

        // Rutas
        origin: null,
        destination: null,
        mode: 'plan', // plan | routes | pick | navigating
        alternatives: [],
        selectedRouteId: null,
        routeInfo: null,
        routing: false,
        currentStep: 0,

        // Navegación
        navigating: false,
        deviating: false,
        distanceRemainingKm: null,
        timeRemainingMin: null,

        // Estados generales
        locating: false,
        locationSet: false,
        status: '',
        toast: null,

        // Capas
        showBicicletas: true,
        showCyclorutas: false,

        // Planificación
        priorizarCiclorutas: true,
        ciclorutaConnection: false,

        init() {
            return init();
        },

        // Búsqueda / lugar
        triggerSearch,
        selectResult,
        setAsOriginFromSearch,
        setAsDestinationFromSearch,

        // Panel
        togglePanel() {
            this.panelCollapsed = !this.panelCollapsed;
        },

        // Origen / destino
        setOriginFromUser,
        setOriginFromSearch: setAsOriginFromSearch,
        setDestinationFromSearch: setAsDestinationFromSearch,
        pickOnMap,
        cancelPick,
        reverseOriginDestination,

        // Rutas
        calculateAlternatives,
        selectAlternative,
        clearRoute,

        // Navegación
        startNavigation,
        stopNavigation,
        recalculateRoute,
        confirmRecalculate,
        dismissDeviation,

        // Capas
        toggleBicicletas() {
            this.showBicicletas = !this.showBicicletas;
            toggleBicicletas(this.showBicicletas);
        },
        toggleCyclorutas() {
            this.showCyclorutas = !this.showCyclorutas;
            toggleCyclorutas(this.showCyclorutas);
        },
        togglePriorizarCiclorutas() {
            togglePriorizarCiclorutas();
        },

        // Formateo
        formatKm(value) {
            return value !== null && value !== undefined ? `${value} km` : '—';
        },
        formatMin(value) {
            return value !== null && value !== undefined ? `${value} min` : '—';
        },
        formatDur(value) {
            if (value === null || value === undefined) return '—';
            const total = Math.max(1, Math.round(Number(value)));
            const h = Math.floor(total / 60);
            const m = total % 60;
            if (h > 0) return `${h} h ${m} min`;
            return `${m} min`;
        },
        routeAvgSpeed(route) {
            if (!route || !route.distance_km || !route.duration_min) return '—';
            const kmh = Math.round((route.distance_km / route.duration_min) * 60);
            return kmh > 0 ? `≈ ${kmh} km/h` : '—';
        },
        originLabel(point) {
            return point ? (point.label || point.name || 'Punto seleccionado') : 'No definido';
        },
        destinationLabel(point) {
            return point ? (point.label || point.name || 'Punto seleccionado') : 'Define un destino';
        },
    };

    // Envolvemos `state` en el proxy reactivo de Alpine ANTES de devolverlo.
    // Las funciones internas (via payload()) mutan este objeto, y así esas
    // mutaciones pasan por el proxy que Alpine observa. Sin esto, se modificaba
    // el objeto raw por fuera del proxy y Alpine no re-evaluaba bindings como
    // :disabled — el botón "Calcular rutas" quedaba disabled aunque ya existieran
    // origen/destino (los marcadores A/B, que son de Leaflet, sí aparecían).
    state = Alpine.reactive(state);

    return state;
}

window.CicleMap = CicleMap;
