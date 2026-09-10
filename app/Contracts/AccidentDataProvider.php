<?php

namespace App\Contracts;

use App\Safety\SafetySignal;

/**
 * Proveedor de datos de siniestralidad vial (datos abiertos oficiales).
 *
 * Regla de honestidad: el factor de siniestralidad solo produce una señal
 * "pesada" cuando el dataset registra sectores críticos reales cerca de la
 * ruta. La ausencia de registros NO implica seguridad: se excluye el factor
 * para no extrapolar cobertura (p. ej. un dataset de carreteras nacionales
 * no sabe nada de las calles urbanas).
 */
interface AccidentDataProvider
{
    public function name(): string;

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, SafetySignal>
     */
    public function signals(array $coordinates): array;

    /**
     * Descripción de la fuente oficial (nombre, URL, licencia, cobertura,
     * actualización) para documentación y transparencia en la UI.
     */
    public function sourceDescription(): array;
}