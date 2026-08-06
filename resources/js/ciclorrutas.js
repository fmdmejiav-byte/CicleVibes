// ============================================================================
// Capa exclusiva de ciclorrutas de Barranquilla (sobre Google Maps).
//
// Cada segmento usa un formato normalizado:
//   {
//     nombre: string,
//     tipo:   clave de TIPOS_CICLORRUTA,
//     path:   [{ lat, lng }, ...],
//   }
//
// Para importar el GeoJSON oficial más adelante, sin cambiar el resto del
// código, solo hay que hacer:
//
//   import geojsonOficial from './ciclorrutas-oficial.geojson';
//   loadBikeRoutes(map, cargarGeoJSON(geojsonOficial));
//
// loadBikeRoutes y cargarGeoJSON aceptan cualquier fuente con la misma forma.
// ============================================================================

// Tipos de infraestructura ciclista: etiqueta, color, grosor de línea.
export const TIPOS_CICLORRUTA = {
    'ciclorruta-en-calzada': { etiqueta: 'Ciclorruta en calzada', color: '#E23B2E', grosor: 6 },
    'ciclorruta-en-anden': { etiqueta: 'Ciclorruta en andén', color: '#FF9500', grosor: 5 },
    ciclobanda: { etiqueta: 'Ciclobanda', color: '#FFD400', grosor: 3 },
    'carril-preferente': { etiqueta: 'Carril ciclo preferente', color: '#1E88E5', grosor: 4 },
};

// Opciones comunes a todas las polylines de ciclorrutas:
// opacidad 100% y zIndex alto para que siempre se vean sobre el mapa.
export const OPCIONES_POLYLINE = {
    strokeOpacity: 1,
    zIndex: 5,
};

// Datos de ejemplo: corredores aproximados de Barranquilla.
// Reemplazar por las coordenadas oficiales cuando estén disponibles.
export const ciclorrutas = [
    {
        nombre: 'Calle 84',
        tipo: 'ciclorruta-en-calzada',
        path: [
            { lat: 10.9992, lng: -74.8076 },
            { lat: 10.9992, lng: -74.7988 },
            { lat: 10.9992, lng: -74.7890 },
            { lat: 10.9992, lng: -74.7830 },
        ],
    },
    {
        nombre: 'Calle 79',
        tipo: 'ciclobanda',
        path: [
            { lat: 10.9946, lng: -74.8060 },
            { lat: 10.9946, lng: -74.7980 },
            { lat: 10.9946, lng: -74.7885 },
            { lat: 10.9946, lng: -74.7820 },
        ],
    },
    {
        nombre: 'Calle 72',
        tipo: 'ciclorruta-en-calzada',
        path: [
            { lat: 10.9891, lng: -74.8130 },
            { lat: 10.9891, lng: -74.8040 },
            { lat: 10.9891, lng: -74.7960 },
            { lat: 10.9891, lng: -74.7870 },
            { lat: 10.9891, lng: -74.7790 },
        ],
    },
    {
        nombre: 'Calle 68',
        tipo: 'ciclorruta-en-anden',
        path: [
            { lat: 10.9851, lng: -74.8080 },
            { lat: 10.9851, lng: -74.7990 },
            { lat: 10.9851, lng: -74.7890 },
        ],
    },
    {
        nombre: 'Calle 93',
        tipo: 'ciclobanda',
        path: [
            { lat: 11.0047, lng: -74.8060 },
            { lat: 11.0047, lng: -74.7975 },
            { lat: 11.0047, lng: -74.7890 },
            { lat: 11.0047, lng: -74.7820 },
        ],
    },
    {
        nombre: 'Carrera 44 / Boulevard del Río',
        tipo: 'ciclorruta-en-anden',
        path: [
            { lat: 10.9690, lng: -74.8060 },
            { lat: 10.9760, lng: -74.8048 },
            { lat: 10.9840, lng: -74.8036 },
            { lat: 10.9920, lng: -74.8024 },
            { lat: 11.0000, lng: -74.8012 },
            { lat: 11.0060, lng: -74.8004 },
        ],
    },
    {
        nombre: 'Carrera 46',
        tipo: 'ciclorruta-en-calzada',
        path: [
            { lat: 10.9670, lng: -74.8004 },
            { lat: 10.9740, lng: -74.8000 },
            { lat: 10.9810, lng: -74.7996 },
            { lat: 10.9880, lng: -74.7992 },
            { lat: 10.9950, lng: -74.7988 },
            { lat: 11.0020, lng: -74.7984 },
        ],
    },
    {
        nombre: 'Carrera 53',
        tipo: 'ciclobanda',
        path: [
            { lat: 10.9680, lng: -74.7922 },
            { lat: 10.9750, lng: -74.7918 },
            { lat: 10.9820, lng: -74.7914 },
            { lat: 10.9890, lng: -74.7910 },
            { lat: 10.9960, lng: -74.7906 },
            { lat: 11.0030, lng: -74.7902 },
        ],
    },
    {
        nombre: 'Vía 40 / Malecón del Río',
        tipo: 'carril-preferente',
        path: [
            { lat: 10.9640, lng: -74.8132 },
            { lat: 10.9720, lng: -74.8120 },
            { lat: 10.9800, lng: -74.8108 },
            { lat: 10.9880, lng: -74.8096 },
            { lat: 10.9960, lng: -74.8084 },
            { lat: 11.0040, lng: -74.8072 },
            { lat: 11.0100, lng: -74.8062 },
        ],
    },
];

// Alias aceptados para el campo "tipo" de un GeoJSON oficial.
const ALIAS_TIPOS = {
    'ciclorruta-en-calzada': 'ciclorruta-en-calzada',
    'ciclorruta en calzada': 'ciclorruta-en-calzada',
    calzada: 'ciclorruta-en-calzada',
    'ciclorruta-en-anden': 'ciclorruta-en-anden',
    'ciclorruta en anden': 'ciclorruta-en-anden',
    'ciclorruta en andén': 'ciclorruta-en-anden',
    anden: 'ciclorruta-en-anden',
    andén: 'ciclorruta-en-anden',
    ciclobanda: 'ciclobanda',
    ciclobandas: 'ciclobanda',
    'carril-preferente': 'carril-preferente',
    'carril ciclo preferente': 'carril-preferente',
    preferente: 'carril-preferente',
};

function normalizarTipo(tipo) {
    const clave = String(tipo || '').trim().toLowerCase();
    return ALIAS_TIPOS[clave] || 'ciclorruta-en-calzada';
}

// Convierte un GeoJSON (FeatureCollection de LineStrings) al formato de
// segmentos que usa loadBikeRoutes. Sin cambios en el resto del código.
export function cargarGeoJSON(geojson) {
    const features = geojson && geojson.type === 'FeatureCollection' ? geojson.features : [];
    return features
        .filter((feature) => feature.geometry && feature.geometry.type === 'LineString')
        .map((feature) => ({
            nombre: feature.properties?.nombre || feature.properties?.name || 'Ciclorruta sin nombre',
            tipo: normalizarTipo(feature.properties?.tipo),
            path: feature.geometry.coordinates.map(([lng, lat]) => ({ lat, lng })),
        }))
        .filter((segmento) => segmento.path.length >= 2);
}

// Dibuja todas las ciclorrutas sobre el mapa como Polylines.
// Devuelve el array de Polylines (útil para ocultarlas/mostrarlas).
export function loadBikeRoutes(map, datos = ciclorrutas) {
    return datos.map((segmento) => {
        const estilo = TIPOS_CICLORRUTA[segmento.tipo] || TIPOS_CICLORRUTA['ciclorruta-en-calzada'];
        return new google.maps.Polyline({
            map,
            path: segmento.path,
            strokeColor: estilo.color,
            strokeWeight: estilo.grosor,
            strokeOpacity: OPCIONES_POLYLINE.strokeOpacity,
            zIndex: OPCIONES_POLYLINE.zIndex,
        });
    });
}

// Construye la leyenda flotante (igual al mapa oficial) dentro del contenedor
// #leyenda-ciclorrutas, leyendo colores y etiquetas de TIPOS_CICLORRUTA.
export function crearLeyendaCiclorrutas() {
    const contenedor = document.getElementById('leyenda-ciclorrutas');
    if (!contenedor) {
        return;
    }

    contenedor.innerHTML = '';

    const titulo = document.createElement('p');
    titulo.className = 'mb-1 text-[10px] font-bold uppercase tracking-wide text-gray-500';
    titulo.textContent = 'Ciclorrutas';
    contenedor.appendChild(titulo);

    Object.values(TIPOS_CICLORRUTA).forEach((estilo) => {
        const item = document.createElement('div');
        item.className = 'flex items-center gap-2 py-0.5 text-xs font-medium text-gray-700';

        const linea = document.createElement('span');
        linea.className = 'h-1.5 w-5 shrink-0 rounded-full';
        linea.style.backgroundColor = estilo.color;

        const texto = document.createElement('span');
        texto.textContent = estilo.etiqueta;

        item.append(linea, texto);
        contenedor.appendChild(item);
    });
}
