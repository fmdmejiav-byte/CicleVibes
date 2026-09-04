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
    // de ciclorrutas de Barranquilla), recoloreados según la paleta neón
    // del tema oscuro CicleVibes.
    const CICLORUTA_TYPES = {
        ciclorruta_calzada: { label: 'Ciclorruta en calzada', color: '#ff4d5a' },
        ciclorruta_anden: { label: 'Ciclorruta en andén', color: '#ff9f43' },
        ciclobanda: { label: 'Ciclobanda', color: '#ffd60a' },
        carril_ciclo_preferente: { label: 'Carril ciclo preferente', color: '#00e5ff' },
    };

    // Glifos SVG (sin emojis): bici, cruce de ubicación y pin de mapa.
    const ICONS = {
        bike: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>',
        user: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2"></path></svg>',
        point: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
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
        map.on('zoomend', applyBikeDensity);
        map.on('moveend', applyBikeDensity);

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
        clickTempMarker = L.marker(latlng, { icon: markerIcon('point', ICONS.point) })
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
                // Primer fix: centrar el mapa una sola vez (getCurrentPosition
                // es de una sola llamada, no se repite en cada actualización).
                map.setView([latitude, longitude], Math.max(map.getZoom(), 14));
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
        // El marcador y el círculo de precisión se crean una sola vez y se
        // actualizan en cada fix GPS. Mantener un único círculo centrado en
        // [lat, lng] con radio = accuracy evita que se acumulen instancias
        // y que el círculo se despegue del marcador.
        const latlng = [lat, lng];

        if (accuracy > 0) {
            if (userAccuracyCircle) {
                userAccuracyCircle.setLatLng(latlng);
                userAccuracyCircle.setRadius(accuracy);
            } else {
                userAccuracyCircle = L.circle(latlng, {
                    radius: accuracy,
                    className: 'leaflet-user-accuracy',
                    color: '#0d9488',
                    fillColor: '#0d9488',
                    fillOpacity: 0.12,
                    weight: 1,
                }).addTo(map);
            }
        } else if (userAccuracyCircle) {
            userAccuracyCircle.remove();
            userAccuracyCircle = null;
        }

        if (userMarker) {
            userMarker.setLatLng(latlng);
        } else {
            const navigating = payload().navigating ? 'navigating' : '';
            userMarker = L.marker(latlng, {
                icon: markerIcon('user', ICONS.user, navigating),
                zIndexOffset: 1000,
            }).addTo(map).bindPopup('Tu posición');
        }

        // Este método NO mueve el mapa. Centrar el mapa es responsabilidad
        // de quien solicita la ubicación (una sola vez) o del modo de
        // seguimiento explícito (navegación).
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

            const layers = buildRouteLayers(coords, isSelected);
            layers.forEach((layer) => layer.addTo(map));
            alternativeLayers.push(...layers);

            if (isSelected) {
                activeRouteLayer = layers[layers.length - 1];
                activeRouteCoords = coords;
            }
        });

        if (activeRouteLayer) {
            const bounds = activeRouteLayer.getBounds();
            map.fitBounds(bounds, { padding: [80, 80] });
        }
    }

    /**
     * Construye las capas de una ruta:
     *  - Activa: halo verde neón difuso + núcleo grueso (estilo premium).
     *  - Alternativa: línea cian discontinua y atenuada para no competir.
     */
    function buildRouteLayers(coords, isSelected) {
        if (!isSelected) {
            return [
                L.polyline(coords, {
                    color: '#00e5ff',
                    weight: 4.5,
                    opacity: 0.4,
                    dashArray: '1 10',
                    lineJoin: 'round',
                    lineCap: 'round',
                }),
            ];
        }

        const halo = L.polyline(coords, {
            color: '#00ff88',
            weight: 11,
            opacity: 0.22,
            lineJoin: 'round',
            lineCap: 'round',
        });
        const core = L.polyline(coords, {
            color: '#00ff88',
            weight: 5.5,
            opacity: 0.95,
            lineJoin: 'round',
            lineCap: 'round',
        });

        return [halo, core];
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

        const chosenRoute = app.alternatives.find((r) => r.id === app.selectedRouteId) || null;
        applyRouteFixes(chosenRoute);
        app.routeInfo = chosenRoute;

        const initialStats = routeStats(chosenRoute);
        app.distanceRemainingKm = initialStats.distance_km > 0 ? initialStats.distance_km : null;
        app.timeRemainingMin = initialStats.duration_min > 0 ? initialStats.duration_min : null;

        app.currentStep = 0;
        app.deviating = false;
        app.status = 'Navegación iniciada. Síguenos en el mapa.';

        // Durante la navegación reducimos el ruido de bicicletas (zoom >= 17).
        applyBikeDensity();

        if (originMarker) map.removeLayer(originMarker);
        if (destMarker) map.removeLayer(destMarker);
        originMarker = null;
        destMarker = null;

        userLocation = app.origin
            ? { lat: app.origin.lat, lng: app.origin.lng }
            : null;

        // Seguimiento GPS continuo sin recargar la página.
        // Evita watchers duplicados si la navegación se reinicia.
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
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
                applyRouteFixes(route);
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
                const layers = buildRouteLayers(coords, true);
                layers.forEach((layer) => layer.addTo(map));
                alternativeLayers.push(...layers);
                activeRouteLayer = layers[layers.length - 1];
                activeRouteCoords = coords;

                const stats = routeStats(route);
                app.distanceRemainingKm = stats.distance_km;
                app.timeRemainingMin = stats.duration_min;
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

        applyBikeDensity();
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

    // ------------------------- Resumen y consolidación de pasos -------------------------
    /**
     * Resumen real de una ruta: suma la distancia y el tiempo de todos sus
     * tramos. Si el backend ya trae totales correctos ("distance_km" /
     * "duration_min"), los respeta; si vienen en 0 (o no vienen), se
     * reconstruyen a partir de los pasos. Así nunca se muestra "0 km / 1 min",
     * y la primera indicación es coherente con el total del recorrido.
     */
    function routeStats(route) {
        if (!route) return { distance_km: 0, duration_min: 0 };

        let distKm = Number(route.distance_km);
        let durMin = Number(route.duration_min);

        let sumDistM = 0;
        let sumDurS = 0;
        (Array.isArray(route.steps) ? route.steps : []).forEach((s) => {
            sumDistM += Number(s.distance_m) || 0;
            sumDurS += Number(s.duration_seconds) || 0;
        });

        if (!(distKm > 0) && sumDistM > 0) {
            distKm = Math.round((sumDistM / 1000) * 10) / 10;
        }
        if (!(durMin > 0) && sumDurS > 0) {
            durMin = Math.max(1, Math.round(sumDurS / 60));
        }

        return { distance_km: distKm, duration_min: durMin };
    }

    /**
     * Fusiona instrucciones consecutivas repetidas del mismo movimiento
     * ("Continúa por Calle 47" × 4 → una sola indicación con la distancia total
     * de todos los tramos).
     */
    function consolidateSteps(steps) {
        if (!Array.isArray(steps) || steps.length < 2) return Array.isArray(steps) ? steps : [];

        const out = [{ ...steps[0] }];
        for (let i = 1; i < steps.length; i++) {
            const prev = out[out.length - 1];
            const cur = steps[i];
            if (normalizedInstruction(prev.instruction) === normalizedInstruction(cur.instruction)) {
                prev.distance_m = (Number(prev.distance_m) || 0) + (Number(cur.distance_m) || 0);
                prev.duration_seconds = (Number(prev.duration_seconds) || 0) + (Number(cur.duration_seconds) || 0);
            } else {
                out.push({ ...cur });
            }
        }
        return out;
    }

    /** Normaliza una instrucción; los números se reemplazan (Calle 47 ≈ Calle 44). */
    function normalizedInstruction(inst) {
        return String(inst || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/\d+/g, '#')
            .trim();
    }

    /**
     * Prepara una ruta para su consumo: consolida pasos repetidos y corrige
     * distancia/duración con el resumen real.
     */
    function applyRouteFixes(route) {
        if (!route) return;
        if (Array.isArray(route.steps)) route.steps = consolidateSteps(route.steps);
        const stats = routeStats(route);
        route.distance_km = stats.distance_km;
        route.duration_min = stats.duration_min;
    }

    // ------------------------- Bicicletas -------------------------
    let bikeMarkers = [];
    let bikesVisible = true;

    function loadBicicletas() {
        bikeMarkers.forEach(({ marker }) => { if (map.hasLayer(marker)) map.removeLayer(marker); });
        bikeMarkers = [];

        axios
            .get('/api/maps/bicicletas')
            .then((res) => {
                const bikes = res.data.bicicletas || [];
                bikeMarkers = bikes.map((bike) => {
                    const marker = L.marker([bike.latitude, bike.longitude], { icon: markerIcon('bike', ICONS.bike) })
                        .bindPopup(`
                            <div style="min-width:170px;">
                                <strong style="color:#00ff88;">${bike.marca} ${bike.modelo}</strong><br>
                                <span style="color:#a7b8b2;">${bike.tipo}</span><br>
                                <span style="color:#a7b8b2;">${bike.barrio}</span><br>
                                <span style="font-size:12px;color:#6f817a;">Dueño: ${bike.dueno}</span>
                            </div>`);
                    return { marker, latlng: [Number(bike.latitude), Number(bike.longitude)] };
                });
                applyBikeDensity();
            })
            .catch(() => { /* opcional */ });
    }

    /**
     * Reduce el ruido de marcadores según el contexto:
     *  - Solo se dibujan a partir de zoom 14 (ciudad amplia → lienzo limpio).
     *  - Durante la navegación se exige zoom >= 17 para no interferir con la ruta.
     *  - Además solo se muestran los que están dentro de la vista actual.
     */
    function applyBikeDensity() {
        if (!bikesVisible || !map) return;
        const zoom = map.getZoom();
        const bounds = map.getBounds();
        const navigating = payload().navigating;
        const minZoom = navigating ? 17 : 14;

        bikeMarkers.forEach(({ marker, latlng }) => {
            const visible = zoom >= minZoom && bounds.contains(latlng);
            if (visible && !map.hasLayer(marker)) marker.addTo(map);
            if (!visible && map.hasLayer(marker)) map.removeLayer(marker);
        });
    }

    function toggleBicicletas(show) {
        bikesVisible = show;
        if (show) {
            applyBikeDensity();
        } else {
            bikeMarkers.forEach(({ marker }) => { if (map.hasLayer(marker)) map.removeLayer(marker); });
        }
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
                const { label } = cyclorutaTypeInfo(p.type);
                const name = [label, p.name ? ` · ${p.name}` : ''].filter(Boolean).join('');
                if (name) layer.bindPopup(`<strong>Cicloruta</strong><br><span style="color:#a7b8b2;">${name}</span>`);
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
        legendOpen: true,

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
        routeStats,
        toggleLegend() {
            this.legendOpen = !this.legendOpen;
        },
        originLabel(point) {
            return point ? (point.label || point.name || 'Punto seleccionado') : 'No definido';
        },
        destinationLabel(point) {
            return point ? (point.label || point.name || 'Punto seleccionado') : 'Define un destino';
        },
        selectedRoute() {
            return this.alternatives.find((r) => r.id === this.selectedRouteId) || null;
        },
        formatDist(meters) {
            const m = Number(meters) || 0;
            if (m <= 0) return '';
            if (m < 1000) return `${Math.round(m)} m`;
            return `${Math.round(m / 100) / 10} km`;
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
