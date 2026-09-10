<?php

namespace App\Contracts;

use App\Safety\SafetySignal;

/**
 * Proveedor de señales de seguridad basadas en infraestructura y entorno
 * vial (OpenStreetMap vía Overpass, red local de ciclorrutas, etc.).
 *
 * Cada proveedor analiza la geometría de la ruta con datos REALES y
 * devuelve una colección de señales verificables. Si no pudo obtener datos,
 * devuelve un set vacío (nunca inventa señales).
 */
interface SafetyDataProvider
{
    /**
     * Nombre legible de la fuente (aparece en la UI y en la documentación de
     * cobertura).
     */
    public function name(): string;

    /**
     * Analiza una ruta y devuelve sus señales de seguridad.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, SafetySignal>
     */
    public function signals(array $coordinates): array;

    /**
     * Metadatos de la fuente para documentación/auditoría: nombre, URL,
     * licencia, cobertura, frecuencia de actualización, límites.
     */
    public function sourceDescription(): array;
}