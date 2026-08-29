#!/usr/bin/env python3
"""Fase 3: Red de ciclorrutas de Barranquilla = cartografía oficial + OSM deduplicada.

Genera resources/data/ciclorutas-barranquilla.geojson combinando la capa
oficial del Distrito (ArcGIS FeatureServer "Ciclovias_BAQ") con los datos ya
importados de OpenStreetMap, eliminando los segmentos OSM que duplican un
feature oficial (solape mutuo ≥ 60 % dentro de 20 m), conservando los
corredores emblemáticos (Troncal, Las Flores, Puerto Mocho) y los fragmentos
no cubiertos por la capa oficial.

Uso:
    python build_red_fase3.py [--cache deposito.geojson]

Sin --cache se descarga la capa oficial por HTTP. Es autónomo (solo stdlib) y
determinista: las features OSM de entrada son las que ya están en el GeoJSON
actual (properties.source == "osm"), sin llamar a Overpass.
"""

import argparse
import json
import math
import sys
import urllib.request
from collections import defaultdict

OFFICIAL_LAYER_URL = (
    "https://services3.arcgis.com/oGYAc07w6wsvgUYr/ArcGIS/rest/services/"
    "Ciclovias_BAQ/FeatureServer/1/query?where=1%3D1&outFields=*&returnGeometry=true"
    "&f=geojson&outSR=4326"
)

# Capa oficial de referencia PDF biciruta-10-08-2022 (PET 2022): 83,10 km.
OFFICIAL_TOTAL_KM = 83.10

# Regla de deduplicación OSM vs oficial.
BUFFER_M = 20.0          # radio alrededor de la geometría oficial
COVERAGE_FRAC = 0.60     # solape mutuo mínimo para considerar duplicado
SAMPLE_STEP_M = 5.0

# Corredores emblemáticos que se conservan aunque un feature oficial exista.
PRESERVED_KEYWORDS = ["troncal", "las flores", "puerto mocho"]

# Mapeo Tipo (capa oficial) -> tipo del mapa PET (aplicación).
TIPO_MAP = {
    "Ciclobanda": "ciclobanda",
    "Ciclopreferente": "carril_ciclo_preferente",
    "Ciclorruta en Andén": "ciclorruta_anden",
    "Ciclorruta en Vía": "ciclorruta_calzada",
}

EARTH_R = 6371000.0


def haversine_m(a, b):
    p1 = a[1] * math.pi / 180
    p2 = b[1] * math.pi / 180
    dp = (b[1] - a[1]) * math.pi / 180
    dl = (b[0] - a[0]) * math.pi / 180
    h = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * EARTH_R * math.asin(math.sqrt(h))


def seg_len_m(coords):
    total = 0.0
    for i in range(len(coords) - 1):
        total += haversine_m(coords[i], coords[i + 1])
    return total


def sample_points(coords, step_m=SAMPLE_STEP_M):
    pts = [coords[0]]
    for i in range(len(coords) - 1):
        d = haversine_m(coords[i], coords[i + 1])
        n = max(1, int(round(d / step_m)))
        for k in range(1, n + 1):
            t = k / n
            lat = coords[i][1] + (coords[i + 1][1] - coords[i][1]) * t
            lng = coords[i][0] + (coords[i + 1][0] - coords[i][0]) * t
            pts.append((lng, lat))
    return pts


def point_to_line_m(p, a, b):
    lat, lng = p[1], p[0]
    ax, ay = a[1], a[0]
    bx, by = b[1], b[0]
    ab_lat = bx - ax
    ab_lng = by - ay
    ap_lat = lat - ax
    ap_lng = lng - ay
    ab2 = ab_lat ** 2 + ab_lng ** 2
    t = 0.0 if ab2 <= 0 else min(1.0, max(0.0, (ap_lat * ab_lat + ap_lng * ab_lng) / ab2))
    cx = ax + t * ab_lat
    cy = ay + t * ab_lng
    d_lat = (lat - cx) * 111320.0
    d_lng = (lng - cy) * 111320.0 * math.cos(cx * math.pi / 180)
    return math.sqrt(d_lat ** 2 + d_lng ** 2)


class Grid:
    """Malla espacial simple para búsquedas de proximidad (metros)."""

    def __init__(self, step_m=10.0):
        self.step = step_m / 111320.0
        self.cells = defaultdict(list)

    @staticmethod
    def _key(lat, lng, step):
        return (int(math.floor(lat / step)), int(math.floor(lng / step)))

    def add(self, lat, lng, item):
        self.cells[self._key(lat, lng, self.step)].append(item)

    def near(self, lat, lng, ring=4):
        kx, ky = self._key(lat, lng, self.step)
        out = []
        for dx in range(-ring, ring + 1):
            for dy in range(-ring, ring + 1):
                out.extend(self.cells[(kx + dx, ky + dy)])
        return out


def download_official(url):
    print(f"Descargando capa oficial: {url}")
    with urllib.request.urlopen(url, timeout=60) as r:
        return json.load(r)


def load_json(path):
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def official_features(data):
    """Features oficiales con geometría válida, mapeados al tipo de la app."""
    feats = []
    for f in data.get("features", []):
        geom = f.get("geometry")
        if not geom or not geom.get("coordinates") or len(geom["coordinates"]) < 2:
            continue
        tipo = f["properties"].get("Tipo") or ""
        app_type = TIPO_MAP.get(tipo)
        if app_type is None:
            print(f"  [!] Tipo oficial desconocido: {tipo!r}", file=sys.stderr)
        nome = f["properties"].get("Nombre") or ""
        length_m = seg_len_m(geom["coordinates"])
        feats.append({
            "type": "Feature",
            "properties": {
                "name": nome,
                "type": app_type,
                "source": "official",
                "official_objectid": f["properties"].get("OBJECTID"),
                "official_tipo": tipo,
                "official_nombre": nome,
                "official_shape_length_m": f["properties"].get("Shape__Length") or 0.0,
                "official_global_id": f["properties"].get("GlobalID"),
                "length_m": round(length_m, 1),
            },
            "geometry": geom,
        })
    return feats


def dedupe_osmosm(osm_feats, off_geoms):
    """Elimina features OSM que duplican un feature oficial.

    Duplicado = solape mutuo >= COVERAGE_FRAC con un mismo feature oficial
    dentro de BUFFER_M: >= 60 % del feature OSM cae dentro de la geometría
    oficial y >= 60 % de la geometría oficial cae dentro del feature OSM.
    Conserva siempre los corredores PRESERVED_KEYWORDS.
    """
    osm_samples = [sample_points(f["geometry"]["coordinates"]) for f in osm_feats]
    osm_lens = [seg_len_m(f["geometry"]["coordinates"]) for f in osm_feats]
    off_samples = [sample_points(f["coordinates"]) for f in off_geoms]
    off_lens = [seg_len_m(f["coordinates"]) for f in off_geoms]

    off_grid = Grid()
    off_segs = []
    for geom in off_geoms:
        c = geom["coordinates"]
        for i in range(len(c) - 1):
            a, b = c[i], c[i + 1]
            sid = len(off_segs)
            off_segs.append((a, b))
            d = haversine_m(a, b)
            n = max(1, int(round(d / 10.0)))
            for k in range(n + 1):
                t = k / n
                off_grid.add(a[1] + (b[1] - a[1]) * t, a[0] + (b[0] - a[0]) * t, sid)

    removed = []
    kept = []
    removed_km = 0.0
    kept_km = 0.0

    for i, f in enumerate(osm_feats):
        name = (f["properties"].get("name") or "")
        nlow = name.lower()
        preserved = any(k in nlow for k in PRESERVED_KEYWORDS)
        c = f["geometry"]["coordinates"]
        oms = osm_samples[i]

        tgrid = Grid()
        segs = []
        for j in range(len(c) - 1):
            a, b = c[j], c[j + 1]
            segs.append((a, b))
            d = haversine_m(a, b)
            n = max(1, int(round(d / 10.0)))
            for k in range(n + 1):
                t = k / n
                tgrid.add(a[1] + (b[1] - a[1]) * t, a[0] + (b[0] - a[0]) * t, j)

        best_min = 0.0
        best_oi = None

        for oi, ots in enumerate(off_samples):
            n_osm_cov = 0
            for lng, lat in oms:
                best = 1e9
                for sid in off_grid.near(lat, lng, 4):
                    a, b = off_segs[sid]
                    d = point_to_line_m((lng, lat), a, b)
                    if d < best:
                        best = d
                    if best <= BUFFER_M:
                        break
                if best <= BUFFER_M:
                    n_osm_cov += 1
            fr_osm = n_osm_cov / len(oms) if oms else 0.0
            if fr_osm < COVERAGE_FRAC:
                continue

            n_off_cov = 0
            for lng, lat in ots:
                best = 1e9
                for sid in tgrid.near(lat, lng, 4):
                    a, b = segs[sid]
                    d = point_to_line_m((lng, lat), a, b)
                    if d < best:
                        best = d
                    if best <= BUFFER_M:
                        break
                if best <= BUFFER_M:
                    n_off_cov += 1
            fr_off = n_off_cov / len(ots) if ots else 0.0

            mutual = min(fr_osm, fr_off)
            if mutual > best_min:
                best_min = mutual
                best_oi = oi

        is_dup = best_min >= COVERAGE_FRAC and not preserved
        if is_dup:
            removed.append((name, round(osm_lens[i] / 1000.0, 3)))
            removed_km += osm_lens[i]
        else:
            kept.append(f)
            kept_km += osm_lens[i]

    return kept, removed, kept_km, removed_km


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", default="resources/data/ciclorutas-barranquilla.geojson")
    parser.add_argument("--output", default="resources/data/ciclorutas-barranquilla.geojson")
    parser.add_argument("--cache", default=None, help="GeoJSON oficial cacheados (evita descarga)")
    args = parser.parse_args()

    if args.cache:
        official = load_json(args.cache)
    else:
        official = download_official(OFFICIAL_LAYER_URL)

    off_feats = official_features(official)
    print(f"Features oficiales con geometría: {len(off_feats)}")

    current = load_json(args.input)
    osm_feats = [f for f in current.get("features", []) if f["properties"].get("source") == "osm"]
    print(f"Features OSM de entrada: {len(osm_feats)}")

    off_geoms = [f["geometry"] for f in off_feats]
    kept, removed, kept_km, removed_km = dedupe_osmosm(osm_feats, off_geoms)

    print(f"OSM duplicados eliminados: {len(removed)} ({removed_km / 1000.0:.2f} km)")
    print(f"OSM conservados: {len(kept)} ({kept_km / 1000.0:.2f} km)")

    osm_total_km = round(kept_km / 1000.0, 2)
    official_km = round(sum(seg_len_m(f["geometry"]["coordinates"]) for f in off_feats) / 1000.0, 2)

    features = off_feats + kept

    collection = {
        "type": "FeatureCollection",
        "_meta": {
            "title": "Red de ciclorrutas de Barranquilla (CicleVibes)",
            "description": (
                "Red de infraestructura ciclista = cartografía oficial del "
                "Distrito (Ciclovias_BAQ, ArcGIS FeatureServer) + red OpenStreetMap "
                "deduplicada contra la oficial. Los features oficiales conservan su "
                "tipología PET (ciclobanda, ciclo preferente, ciclorruta en calzada/"
                "andén); los features OSM conservan su clasificación heurística."
            ),
            "source": {
                "official": {
                    "url": OFFICIAL_LAYER_URL,
                    "dataset": "Ciclovias_BAQ (Alcaldía Distrital de Barranquilla)",
                    "features": len(off_feats),
                    "mapped_km": official_km,
                },
                "osm": "OpenStreetMap contributors, ODbL (https://www.openstreetmap.org/copyright)",
            },
            "official_total_km": OFFICIAL_TOTAL_KM,
            "official_mapped_km": official_km,
            "osm_total_km": osm_total_km,
            "osm_removed_as_duplicate_km": round(removed_km / 1000.0, 2),
            "queried_at": "2026-08-28",
            "query_bbox": [10.85, -75.0, 11.06, -74.7],
            "dedup_rule": (
                "Se elimina un feature OSM si >= 60% de su longitud y >= 60% de la "
                "longitud de un mismo feature oficial coinciden dentro de 20 m. "
                "Se conservan siempre los corredores Troncal, Las Flores y Puerto Mocho."
            ),
            "counts": {
                "official": len(off_feats),
                "osm": len(kept),
                "total": len(features),
            },
        },
        "features": features,
    }

    with open(args.output, "w", encoding="utf-8") as fh:
        json.dump(collection, fh, ensure_ascii=False, separators=(",", ":"))

    print(f"Escrito: {args.output} ({len(features)} features, {official_km + osm_total_km:.2f} km)")


if __name__ == "__main__":
    main()