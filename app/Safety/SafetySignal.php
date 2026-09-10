<?php

namespace App\Safety;

/**
 * Señal de seguridad real detectada sobre un segmento analizado de ruta.
 *
 * Cada señal representa un dato concreto (proveniente de una fuente
 * verificable) que aporta información o peligro al trayecto. Nunca se
 * construye una señal a partir de suposiciones.
 */
final class SafetySignal
{
    public function __construct(
        public readonly string $type,
        public readonly float $value,
        public readonly float $coverage,
        public readonly string $source,
        public readonly float $confidence,
        public readonly ?array $location = null,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Serialización para los metadatos de la ruta y la UI.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => (float) round($this->value, 1),
            'coverage' => (float) round($this->coverage, 4),
            'source' => $this->source,
            'confidence' => (float) round($this->confidence, 4),
            'location' => $this->location,
            'metadata' => $this->metadata,
        ];
    }
}