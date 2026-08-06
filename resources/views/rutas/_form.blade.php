<div class="mb-4">

    <label class="font-medium text-gray-700">Nombre</label>

    <input
        type="text"
        name="nombre"
        class="border rounded w-full mt-1"
        value="{{ old('nombre', $ruta->nombre ?? '') }}">

    @error('nombre')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror

</div>

<div class="mb-4">

    <label class="font-medium text-gray-700">Descripción</label>

    <textarea
        name="descripcion"
        rows="2"
        class="border rounded w-full mt-1">{{ old('descripcion', $ruta->descripcion ?? '') }}</textarea>

</div>

<div class="grid md:grid-cols-2 gap-4 mb-4">

    <div>
        <label class="font-medium text-gray-700">Punto de origen</label>
        <input
            id="origen-busqueda"
            type="text"
            placeholder="Buscar lugar o toca el mapa…"
            class="border rounded w-full mt-1"
            value="{{ old('origen', $ruta->origen ?? '') }}">
    </div>

    <div>
        <label class="font-medium text-gray-700">Destino</label>
        <input
            id="destino-busqueda"
            type="text"
            placeholder="Buscar lugar o toca el mapa…"
            class="border rounded w-full mt-1"
            value="{{ old('destino', $ruta->destino ?? '') }}">
    </div>

</div>

<input type="hidden" name="origen" value="{{ old('origen', $ruta->origen ?? '') }}">
<input type="hidden" name="destino" value="{{ old('destino', $ruta->destino ?? '') }}">
<input type="hidden" name="distancia" value="{{ old('distancia', $ruta->distancia ?? '') }}">
<input type="hidden" name="duracion" value="{{ old('duracion', $ruta->duracion ?? '') }}">
<input type="hidden" name="polilinea" value="{{ old('polilinea', $ruta->polilinea ?? '') }}">

@error('origen')
    <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
@enderror
@error('destino')
    <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
@enderror

<div
    id="map"
    class="w-full bg-gray-200 rounded-lg shadow"
    style="height: 480px;"></div>

<div class="mt-3 flex items-center justify-between gap-3">

    <div id="info-ruta" class="text-sm text-gray-700">
        Escribe el origen y el destino, o toca el mapa para elegirlos.
    </div>

    <div class="flex items-center gap-2 whitespace-nowrap">

        <button
            type="button"
            id="btn-calcular"
            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
            Calcular ruta
        </button>

        <button
            type="button"
            id="btn-invertir"
            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm">
            ⇅ Invertir
        </button>

    </div>

</div>

<div
    id="instrucciones-panel"
    class="hidden mt-3 bg-white border rounded-lg shadow max-h-96 overflow-y-auto"></div>

@if(config('services.google_maps.key'))
    <script>
        window.initRutaMap = function () {
            const map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 10.9871, lng: -74.7890 },
                zoom: 12,
                mapTypeId: 'roadmap',
                mapTypeControl: true,
            });

            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                panel: document.getElementById('instrucciones-panel'),
                preserveViewport: false,
                draggable: true,
            });

            const ciclorrutas = @json(config('ciclorrutas.segments', []));
            const umbralCiclorruta = @json(config('ciclorrutas.umbral_metros', 1500));
            const maxWaypoints = @json(config('ciclorrutas.max_waypoints', 2));

            let origin = null;
            let destination = null;
            let polilineaPrefill = null;

            const markers = { origin: null, destination: null };
            const infoRuta = document.getElementById('info-ruta');
            const panel = document.getElementById('instrucciones-panel');
            const btnCalcular = document.getElementById('btn-calcular');
            const btnInvertir = document.getElementById('btn-invertir');

            const campo = (nombre) => document.querySelector('input[name="' + nombre + '"]');
            const busqueda = (id) => document.getElementById(id + '-busqueda');

            const coordStr = (valor) => (
                (typeof valor.lat === 'function' ? valor.lat() : valor.lat) + ',' +
                (typeof valor.lng === 'function' ? valor.lng() : valor.lng)
            );

            const extraerPunto = (valor) => ({
                lat: typeof valor.lat === 'function' ? valor.lat() : parseFloat(valor.lat),
                lng: typeof valor.lng === 'function' ? valor.lng() : parseFloat(valor.lng),
            });

            const normalizarPunto = (valor) => {
                if (valor && typeof valor === 'object' && valor.lat !== undefined) {
                    return extraerPunto(valor);
                }
                if (typeof valor === 'string' && valor.includes(',')) {
                    const partes = valor.split(',');
                    const lat = parseFloat(partes[0]);
                    const lng = parseFloat(partes[1]);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        return { lat: lat, lng: lng };
                    }
                }
                return null;
            };

            const setMarker = (tipo, latLng) => {
                if (markers[tipo]) {
                    markers[tipo].setMap(null);
                }
                markers[tipo] = new google.maps.Marker({
                    position: latLng,
                    map: map,
                    draggable: true,
                    title: tipo === 'origin' ? 'Origen' : 'Destino',
                    label: tipo === 'origin' ? 'A' : 'B',
                });
                markers[tipo].addListener('dragend', () => {
                    if (tipo === 'origin') {
                        origin = markers.origin.getPosition();
                    } else {
                        destination = markers.destination.getPosition();
                    }
                    reverseGeocode(markers[tipo].getPosition(), tipo === 'origin' ? 'origen' : 'destino');
                    calcularRuta();
                });
            };

            const reverseGeocode = (latLng, campoNombre) => {
                new google.maps.Geocoder().geocode({ location: latLng }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        busqueda(campoNombre).value = results[0].formatted_address;
                        campo(campoNombre).value = results[0].formatted_address;
                    }
                });
            };

            const limpiarPrefill = () => {
                if (polilineaPrefill) {
                    polilineaPrefill.setMap(null);
                    polilineaPrefill = null;
                }
            };

            const limpiarRuta = () => {
                try {
                    directionsRenderer.set('directions', null);
                } catch (e) {}
                panel.classList.add('hidden');
                limpiarPrefill();
            };

            const autocompletar = (campoNombre) => {
                const autocomplete = new google.maps.places.Autocomplete(busqueda(campoNombre), {
                    fields: ['formatted_address', 'geometry'],
                });
                autocomplete.bindTo('bounds', map);
                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) {
                        return;
                    }
                    const latLng = place.geometry.location;
                    if (campoNombre === 'origen') {
                        origin = latLng;
                        setMarker('origin', latLng);
                    } else {
                        destination = latLng;
                        setMarker('destination', latLng);
                    }
                    limpiarRuta();
                    busqueda(campoNombre).value = place.formatted_address;
                    campo(campoNombre).value = place.formatted_address;
                    map.setCenter(latLng);
                    calcularRuta();
                });
            };

            const distanciaPuntoSegmento = (p, a, b) => {
                const R = 6371000;
                const rad = (d) => d * Math.PI / 180;
                const cosLat = Math.cos(rad((a.lat + b.lat) / 2));
                const ax = a.lng * cosLat;
                const ay = a.lat;
                const bx = b.lng * cosLat;
                const by = b.lat;
                const px = p.lng * cosLat;
                const py = p.lat;
                const dx = bx - ax;
                const dy = by - ay;
                const lenSq = dx * dx + dy * dy;
                let t = lenSq ? ((px - ax) * dx + (py - ay) * dy) / lenSq : 0;
                t = Math.max(0, Math.min(1, t));
                const cx = ax + t * dx;
                const cy = ay + t * dy;
                return R * Math.sqrt(rad(px - cx) ** 2 + rad(py - cy) ** 2);
            };

            const ciclorrutasWaypoints = (origenPunto, destinoPunto) => {
                const candidatos = [];

                ciclorrutas.forEach((segmento) => {
                    const puntos = segmento.puntos || [];
                    for (let i = 0; i < puntos.length - 1; i++) {
                        const a = puntos[i];
                        const b = puntos[i + 1];
                        const distOrigen = distanciaPuntoSegmento(origenPunto, a, b);
                        const distDestino = distanciaPuntoSegmento(destinoPunto, a, b);
                        if (distOrigen <= umbralCiclorruta && distDestino <= umbralCiclorruta) {
                            candidatos.push({ a: a, b: b, desvio: distOrigen + distDestino });
                        }
                    }
                });

                candidatos.sort((x, y) => x.desvio - y.desvio);

                return candidatos.slice(0, maxWaypoints).map((c) => ({
                    location: { lat: (c.a.lat + c.b.lat) / 2, lng: (c.a.lng + c.b.lng) / 2 },
                    stopover: false,
                }));
            };

            const calcularRuta = () => {
                const origenTexto = origin ? coordStr(origin) : (busqueda('origen').value || '');
                const destinoTexto = destination ? coordStr(destination) : (busqueda('destino').value || '');

                if (!origenTexto || !destinoTexto) {
                    infoRuta.textContent = 'Escribe el origen y el destino, o toca el mapa para elegirlos.';
                    return;
                }

                const origenPunto = normalizarPunto(origin ? origin : origenTexto);
                const destinoPunto = normalizarPunto(destination ? destination : destinoTexto);

                infoRuta.textContent = 'Calculando ruta…';
                panel.classList.add('hidden');

                const request = {
                    origin: origenTexto,
                    destination: destinoTexto,
                    travelMode: google.maps.TravelMode.BICYCLING,
                    avoidHighways: true,
                    unitSystem: google.maps.UnitSystem.METRIC,
                };

                if (origenPunto && destinoPunto) {
                    const waypoints = ciclorrutasWaypoints(origenPunto, destinoPunto);
                    if (waypoints.length) {
                        request.waypoints = waypoints;
                    }
                }

                directionsService.route(request, (result, status) => {
                    if (status !== google.maps.DirectionsStatus.OK) {
                        infoRuta.textContent = 'No se pudo calcular la ruta (' + status + '). Verifica origen y destino.';
                        return;
                    }

                    directionsRenderer.setDirections(result);

                    const ruta = result.routes[0];
                    const totalDistancia = ruta.legs.reduce((suma, tramo) => suma + tramo.distance.value, 0);
                    const totalDuracion = ruta.legs.reduce((suma, tramo) => suma + tramo.duration.value, 0);
                    const km = (totalDistancia / 1000).toFixed(2);
                    const min = Math.round(totalDuracion / 60);

                    campo('origen').value = busqueda('origen').value;
                    campo('destino').value = busqueda('destino').value;
                    campo('distancia').value = km;
                    campo('duracion').value = min;
                    campo('polilinea').value = ruta.overview_polyline.points;

                    limpiarPrefill();
                    panel.classList.remove('hidden');
                    infoRuta.textContent = '🚲 Distancia: ' + km + ' km · Tiempo estimado: ' + min + ' min';
                });
            };

            autocompletar('origen');
            autocompletar('destino');

            map.addListener('click', (e) => {
                if (!origin) {
                    origin = e.latLng;
                    setMarker('origin', e.latLng);
                    reverseGeocode(e.latLng, 'origen');
                } else {
                    destination = e.latLng;
                    setMarker('destination', e.latLng);
                    reverseGeocode(e.latLng, 'destino');
                    calcularRuta();
                }
            });

            btnCalcular.addEventListener('click', calcularRuta);

            btnInvertir.addEventListener('click', () => {
                [origin, destination] = [destination, origin];
                [markers.origin, markers.destination] = [markers.destination, markers.origin];
                const tmpBusqueda = busqueda('origen').value;
                const tmpHidden = campo('origen').value;
                busqueda('origen').value = busqueda('destino').value;
                campo('origen').value = campo('destino').value;
                busqueda('destino').value = tmpBusqueda;
                campo('destino').value = tmpHidden;
                if (origin) {
                    setMarker('origin', origin);
                }
                if (destination) {
                    setMarker('destination', destination);
                }
                calcularRuta();
            });

            const coordsIniciales = @json($coordenadasRuta ?? []);
            if (coordsIniciales.length >= 2) {
                origin = coordsIniciales[0];
                destination = coordsIniciales[coordsIniciales.length - 1];
                setMarker('origin', origin);
                setMarker('destination', destination);
                polilineaPrefill = new google.maps.Polyline({
                    path: coordsIniciales,
                    map: map,
                    strokeColor: '#2563eb',
                    strokeWeight: 6,
                    strokeOpacity: 0.9,
                });
                const bounds = new google.maps.LatLngBounds();
                coordsIniciales.forEach((punto) => bounds.extend(punto));
                map.fitBounds(bounds);
            }
        };
    </script>
    <script
        async
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&loading=async&callback=initRutaMap&v=weekly&libraries=places"></script>
@else
    <p class="text-sm text-amber-600 mt-2">
        Configura <code>GOOGLE_MAPS_API_KEY</code> en tu <code>.env</code> para usar el mapa.
    </p>
@endif
