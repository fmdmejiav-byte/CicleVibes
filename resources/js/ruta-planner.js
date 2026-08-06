import { ciclorrutas, TIPOS_CICLORRUTA, loadBikeRoutes, crearLeyendaCiclorrutas } from './ciclorrutas';

const CENTRO_BARRANQUILLA = { lat: 10.9871, lng: -74.7890 };
const UMBRAL_WAYPOINT_METROS = 1500;
const MAX_WAYPOINTS = 2;
const UMBRAL_CERCANIA_METROS = 60;
const INTERVALO_MUESTREO_METROS = 100;
const FRACCION_APROVECHA = 0.25;

const RAIZ = document.getElementById('ruta-planner');
let temporizadorRedimension = null;

if (RAIZ && RAIZ.dataset.mapKey) {
    main(RAIZ);
}

async function main(raiz) {
    try {
        await cargarGoogleMaps(raiz.dataset.mapKey);
    } catch {
        mostrarFalloCarga();
        return;
    }

    const mapa = new google.maps.Map(document.getElementById('map'), {
        center: CENTRO_BARRANQUILLA,
        zoom: 12,
        mapTypeId: 'roadmap',
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
        zoomControl: true,
    });

    google.maps.event.addListenerOnce(mapa, 'idle', () => {
        console.info('Mapa de Google cargado correctamente');
    });

    window.addEventListener('resize', () => {
        clearTimeout(temporizadorRedimension);
        temporizadorRedimension = setTimeout(() => {
            google.maps.event.trigger(mapa, 'resize');
        }, 200);
    });

    const servicioDirecciones = new google.maps.DirectionsService();
    const rendererRuta = new google.maps.DirectionsRenderer({
        map: mapa,
        preserveViewport: false,
        draggable: true,
        suppressMarkers: true,
        // La ruta calculada se dibuja en verde brillante, más gruesa y con
        // zIndex mayor que las ciclorrutas para quedar siempre encima.
        polylineOptions: {
            strokeColor: '#22C55E',
            strokeOpacity: 1,
            strokeWeight: 8,
            zIndex: 20,
        },
    });

    const marcadores = { origen: null, destino: null };
    let origen = null;
    let destino = null;

    const el = {
        inputOrigen: document.getElementById('origen-input'),
        inputDestino: document.getElementById('destino-input'),
        btnBuscar: document.getElementById('btn-buscar'),
        btnInvertir: document.getElementById('btn-invertir'),
        btnIndicaciones: document.getElementById('btn-indicaciones'),
        panelIndicaciones: document.getElementById('panel-indicaciones'),
        contenedorIndicaciones: document.getElementById('contenedor-indicaciones'),
        resumen: document.getElementById('resumen'),
        resumenDistancia: document.getElementById('resumen-distancia'),
        resumenTiempo: document.getElementById('resumen-tiempo'),
        resumenSeguridad: document.getElementById('resumen-seguridad'),
        resumenInfra: document.getElementById('resumen-infra'),
        mensajeRecomendacion: document.getElementById('mensaje-recomendacion'),
        btnGuardar: document.getElementById('btn-guardar'),
        estadoRuta: document.getElementById('estado-ruta'),
        mostrarCiclorrutas: document.getElementById('mostrar-ciclorrutas'),
        inputNombre: document.getElementById('input-nombre'),
        inputOrigenHidden: document.getElementById('input-origen'),
        inputDestinoHidden: document.getElementById('input-destino'),
        inputDistancia: document.getElementById('input-distancia'),
        inputDuracion: document.getElementById('input-duracion'),
        inputPolilinea: document.getElementById('input-polilinea'),
        formRuta: document.getElementById('form-ruta'),
    };

    // Capa de ciclorrutas: se dibujan las polylines y la leyenda flotante.
    const polilinasCiclorrutas = loadBikeRoutes(mapa);
    crearLeyendaCiclorrutas();
    el.mostrarCiclorrutas.addEventListener('change', () => {
        polilinasCiclorrutas.forEach((p) => p.setMap(el.mostrarCiclorrutas.checked ? mapa : null));
    });

    configurarAutocompletar(el.inputOrigen, 'origen');
    configurarAutocompletar(el.inputDestino, 'destino');

    mapa.addListener('click', (evento) => {
        const campo = origen ? 'destino' : 'origen';
        if (campo === 'origen') {
            origen = evento.latLng;
        } else {
            destino = evento.latLng;
        }
        colocarMarcador(campo, evento.latLng);
        invertirGeocodificacion(evento.latLng, campo);
        calcularRuta();
    });

    el.btnBuscar.addEventListener('click', calcularRuta);
    el.btnInvertir.addEventListener('click', invertirOrigenDestino);

    document.querySelectorAll('input[name="tipo-ruta"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (origen || destino) {
                calcularRuta();
            }
        });
    });

    [el.inputOrigen, el.inputDestino].forEach((input) => {
        input.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                calcularRuta();
            }
        });
    });

    el.btnIndicaciones.addEventListener('click', () => {
        const oculto = el.panelIndicaciones.classList.toggle('hidden');
        el.btnIndicaciones.lastChild.textContent = oculto ? 'Ver indicaciones paso a paso' : 'Ocultar indicaciones';
    });

    el.formRuta.addEventListener('submit', (evento) => {
        if (el.btnGuardar.disabled) {
            evento.preventDefault();
            calcularRuta();
        }
    });

    function configurarAutocompletar(input, campo) {
        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['formatted_address', 'geometry'],
        });
        autocomplete.bindTo('bounds', mapa);
        autocomplete.addListener('place_changed', () => {
            const lugar = autocomplete.getPlace();
            if (!lugar.geometry) {
                return;
            }
            if (campo === 'origen') {
                origen = lugar.geometry.location;
            } else {
                destino = lugar.geometry.location;
            }
            colocarMarcador(campo, lugar.geometry.location);
            input.value = lugar.formatted_address;
            mapa.setCenter(lugar.geometry.location);
            calcularRuta();
        });
    }

    function colocarMarcador(campo, latLng) {
        if (marcadores[campo]) {
            marcadores[campo].setMap(null);
        }
        marcadores[campo] = new google.maps.Marker({
            position: latLng,
            map: mapa,
            draggable: true,
            label: campo === 'origen' ? 'A' : 'B',
            title: campo === 'origen' ? 'Lugar de inicio' : 'Lugar de destino',
        });
        marcadores[campo].addListener('dragend', () => {
            if (campo === 'origen') {
                origen = marcadores.origen.getPosition();
            } else {
                destino = marcadores.destino.getPosition();
            }
            calcularRuta();
        });
    }

    function invertirGeocodificacion(latLng, campo) {
        const input = campo === 'origen' ? el.inputOrigen : el.inputDestino;
        input.value = coordStr(latLng);
        new google.maps.Geocoder().geocode({ location: latLng }, (resultados, estado) => {
            if (estado === 'OK' && resultados[0]) {
                input.value = resultados[0].formatted_address;
            }
        });
    }

    function invertirOrigenDestino() {
        [origen, destino] = [destino, origen];
        const textoOrigen = el.inputOrigen.value;
        el.inputOrigen.value = el.inputDestino.value;
        el.inputDestino.value = textoOrigen;
        if (marcadores.origen) {
            marcadores.origen.setPosition(origen);
        }
        if (marcadores.destino) {
            marcadores.destino.setPosition(destino);
        }
        if (origen && !marcadores.origen) {
            colocarMarcador('origen', origen);
        }
        if (destino && !marcadores.destino) {
            colocarMarcador('destino', destino);
        }
        calcularRuta();
    }

    function calcularRuta() {
        const origenTexto = origen ? coordStr(origen) : el.inputOrigen.value.trim();
        const destinoTexto = destino ? coordStr(destino) : el.inputDestino.value.trim();

        if (!origenTexto || !destinoTexto) {
            el.resumen.classList.add('hidden');
            el.estadoRuta.textContent = 'Escribe el inicio y el destino, o toca el mapa.';
            return;
        }

        const tipoRuta = document.querySelector('input[name="tipo-ruta"]:checked').value;
        const origenPunto = origen ? aPlain(origen) : parseCoords(origenTexto);
        const destinoPunto = destino ? aPlain(destino) : parseCoords(destinoTexto);

        el.estadoRuta.textContent = 'Calculando ruta…';
        el.btnBuscar.disabled = true;

        const request = {
            origin: origenTexto,
            destination: destinoTexto,
            travelMode: google.maps.TravelMode.BICYCLING,
            unitSystem: google.maps.UnitSystem.METRIC,
        };

        if (tipoRuta === 'segura' || tipoRuta === 'trafico') {
            request.avoidHighways = true;
        }

        if (tipoRuta === 'segura' && origenPunto && destinoPunto) {
            const waypoints = seleccionarWaypointsCiclorrutas(origenPunto, destinoPunto);
            if (waypoints.length) {
                request.waypoints = waypoints;
            }
        }

        servicioDirecciones.route(request, (resultado, estado) => {
            el.btnBuscar.disabled = false;
            if (estado !== google.maps.DirectionsStatus.OK) {
                el.estadoRuta.textContent = 'No se pudo calcular la ruta. Revisa el inicio y el destino.';
                return;
            }
            el.estadoRuta.textContent = '';
            rendererRuta.setDirections(resultado);
            actualizarDesdeResultado(resultado);
        });
    }

    function actualizarDesdeResultado(resultado) {
        const ruta = resultado.routes[0];
        const distanciaTotal = ruta.legs.reduce((suma, tramo) => suma + tramo.distance.value, 0);
        const duracionTotal = ruta.legs.reduce((suma, tramo) => suma + tramo.duration.value, 0);
        const km = (distanciaTotal / 1000).toFixed(2);
        const minutos = Math.round(duracionTotal / 60);

        const evaluacion = evaluarInfraestructura(resultado);

        el.resumenDistancia.textContent = `${km} km`;
        el.resumenTiempo.textContent = `${minutos} min`;
        el.resumenSeguridad.textContent = nivelSeguridad(evaluacion.fraccion);
        el.resumenInfra.textContent = evaluacion.tipoDominante
            ? (TIPOS_CICLORRUTA[evaluacion.tipoDominante]?.etiqueta || evaluacion.tipoDominante)
            : 'Sin ciclorruta cercana';

        mostrarRecomendacion(evaluacion.fraccion);
        construirPasos(resultado);

        el.inputOrigenHidden.value = el.inputOrigen.value;
        el.inputDestinoHidden.value = el.inputDestino.value;
        el.inputDistancia.value = km;
        el.inputDuracion.value = minutos;
        el.inputPolilinea.value = ruta.overview_polyline.points;
        el.inputNombre.value = (el.inputOrigen.value + ' → ' + el.inputDestino.value).slice(0, 150) || 'Ruta en bicicleta';

        el.btnGuardar.disabled = false;
        el.btnIndicaciones.classList.remove('hidden');
        el.btnIndicaciones.classList.add('flex');
        el.panelIndicaciones.classList.add('hidden');
        el.resumen.classList.remove('hidden');
    }

    function mostrarRecomendacion(fraccion) {
        const aprovecha = fraccion >= FRACCION_APROVECHA;
        el.mensajeRecomendacion.classList.remove('hidden');
        el.mensajeRecomendacion.classList.toggle('bg-green-50', aprovecha);
        el.mensajeRecomendacion.classList.toggle('text-green-800', aprovecha);
        el.mensajeRecomendacion.classList.toggle('border', aprovecha);
        el.mensajeRecomendacion.classList.toggle('border-green-200', aprovecha);
        el.mensajeRecomendacion.classList.toggle('bg-amber-50', !aprovecha);
        el.mensajeRecomendacion.classList.toggle('text-amber-800', !aprovecha);
        el.mensajeRecomendacion.classList.toggle('border', !aprovecha);
        el.mensajeRecomendacion.classList.toggle('border-amber-200', !aprovecha);
        el.mensajeRecomendacion.innerHTML = aprovecha
            ? '<span class="font-semibold">✓</span> Esta ruta aprovecha infraestructura ciclista.'
            : '<span class="font-semibold">!</span> Ruta parcialmente protegida.';
    }

    function construirPasos(resultado) {
        const pasos = resultado.routes[0].legs.flatMap((tramo) => tramo.steps);
        el.contenedorIndicaciones.innerHTML = '';

        pasos.forEach((paso, indice) => {
            const fila = document.createElement('li');
            fila.className = 'flex items-start gap-3 border-b border-gray-100 py-2.5 last:border-0';

            const numero = document.createElement('span');
            numero.className = 'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700';
            numero.textContent = String(indice + 1);

            const cuerpo = document.createElement('div');
            cuerpo.className = 'min-w-0 flex-1';

            const instrucciones = document.createElement('div');
            instrucciones.className = 'text-gray-800';
            instrucciones.innerHTML = paso.instructions;

            const meta = document.createElement('div');
            meta.className = 'mt-0.5 text-xs text-gray-400';
            meta.textContent = `${paso.distance.text} · ${paso.duration.text}`;

            cuerpo.append(instrucciones, meta);
            fila.append(numero, cuerpo);
            el.contenedorIndicaciones.appendChild(fila);
        });
    }
}

function cargarGoogleMaps(clave) {
    if (window.google && window.google.maps && window.google.maps.Map) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const temporizador = setTimeout(() => {
            reject(new Error('Google Maps tardó demasiado en cargar'));
        }, 20000);

        window.__ciclevibesMapaListo = () => {
            clearTimeout(temporizador);
            resolve();
        };

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(clave)}&v=weekly&loading=async&callback=__ciclevibesMapaListo&libraries=places,routes,geometry,marker`;
        script.async = true;
        script.onerror = () => {
            clearTimeout(temporizador);
            reject(new Error('No se pudo cargar el script de Google Maps'));
        };
        document.head.appendChild(script);
    });
}

function mostrarFalloCarga() {
    const contenedor = document.getElementById('map');
    contenedor.innerHTML = '<div class="flex h-full items-center justify-center bg-gray-200 p-6 text-center text-sm text-gray-600">No se pudo cargar Google Maps. Revisa tu conexión y que <code class="font-mono">GOOGLE_MAPS_API_KEY</code> esté configurada.</div>';
}

const rad = (grados) => (grados * Math.PI) / 180;

function coordStr(valor) {
    return `${typeof valor.lat === 'function' ? valor.lat() : valor.lat},${typeof valor.lng === 'function' ? valor.lng() : valor.lng}`;
}

function aPlain(valor) {
    return {
        lat: typeof valor.lat === 'function' ? valor.lat() : parseFloat(valor.lat),
        lng: typeof valor.lng === 'function' ? valor.lng() : parseFloat(valor.lng),
    };
}

function parseCoords(texto) {
    if (!texto || !texto.includes(',')) {
        return null;
    }
    const partes = texto.split(',');
    const lat = parseFloat(partes[0]);
    const lng = parseFloat(partes[1]);
    if (isNaN(lat) || isNaN(lng)) {
        return null;
    }
    return { lat, lng };
}

function distanciaPuntoASegmento(punto, a, b) {
    const R = 6371000;
    const cosLat = Math.cos(rad((a.lat + b.lat) / 2));
    const ax = a.lng * cosLat;
    const ay = a.lat;
    const bx = b.lng * cosLat;
    const by = b.lat;
    const px = punto.lng * cosLat;
    const py = punto.lat;
    const dx = bx - ax;
    const dy = by - ay;
    const longitudCuadrada = dx * dx + dy * dy;
    let t = longitudCuadrada ? ((px - ax) * dx + (py - ay) * dy) / longitudCuadrada : 0;
    t = Math.max(0, Math.min(1, t));
    return R * Math.sqrt(rad(px - (ax + t * dx)) ** 2 + rad(py - (ay + t * dy)) ** 2);
}

function distanciaPuntoACiclorruta(punto, path) {
    const puntoLlano = aPlain(punto);
    let min = Infinity;
    for (let i = 0; i < path.length - 1; i++) {
        const d = distanciaPuntoASegmento(puntoLlano, path[i], path[i + 1]);
        if (d < min) {
            min = d;
        }
    }
    return min;
}

function seleccionarWaypointsCiclorrutas(origenPunto, destinoPunto) {
    const candidatos = [];

    ciclorrutas.forEach((segmento) => {
        const path = segmento.path;
        for (let i = 0; i < path.length - 1; i++) {
            const a = path[i];
            const b = path[i + 1];
            const distOrigen = distanciaPuntoASegmento(origenPunto, a, b);
            const distDestino = distanciaPuntoASegmento(destinoPunto, a, b);
            if (distOrigen <= UMBRAL_WAYPOINT_METROS && distDestino <= UMBRAL_WAYPOINT_METROS) {
                candidatos.push({ a, b, desvio: distOrigen + distDestino });
            }
        }
    });

    candidatos.sort((x, y) => x.desvio - y.desvio);

    return candidatos.slice(0, MAX_WAYPOINTS).map((c) => ({
        location: { lat: (c.a.lat + c.b.lat) / 2, lng: (c.a.lng + c.b.lng) / 2 },
        stopover: false,
    }));
}

function evaluarInfraestructura(resultado) {
    const ruta = resultado.routes[0];
    const puntosDecodificados = google.maps.geometry.encoding.decodePath(ruta.overview_polyline);
    const muestras = muestrearPuntos(puntosDecodificados);

    let cerca = 0;
    const conteoPorTipo = {};

    muestras.forEach((punto) => {
        let min = Infinity;
        let tipoMin = null;
        ciclorrutas.forEach((segmento) => {
            const d = distanciaPuntoACiclorruta(punto, segmento.path);
            if (d < min) {
                min = d;
                tipoMin = segmento.tipo;
            }
        });
        if (min <= UMBRAL_CERCANIA_METROS) {
            cerca += 1;
            conteoPorTipo[tipoMin] = (conteoPorTipo[tipoMin] || 0) + 1;
        }
    });

    const fraccion = muestras.length ? cerca / muestras.length : 0;
    const tipoDominante = Object.keys(conteoPorTipo).sort((a, b) => conteoPorTipo[b] - conteoPorTipo[a])[0] || null;

    return { fraccion, tipoDominante };
}

function muestrearPuntos(puntos) {
    const muestras = [];
    let acumulado = 0;
    for (let i = 1; i < puntos.length; i++) {
        acumulado += google.maps.geometry.spherical.computeDistanceBetween(puntos[i - 1], puntos[i]);
        if (acumulado >= INTERVALO_MUESTREO_METROS) {
            muestras.push(puntos[i]);
            acumulado = 0;
        }
    }
    if (puntos.length) {
        muestras.push(puntos[puntos.length - 1]);
    }
    return muestras;
}

function nivelSeguridad(fraccion) {
    if (fraccion >= 0.5) {
        return 'Alto';
    }
    if (fraccion >= 0.2) {
        return 'Medio';
    }
    return 'Bajo';
}
