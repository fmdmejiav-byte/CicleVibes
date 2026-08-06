<?php

namespace App\Support;

/**
 * Codificación y decodificación de polilíneas estilo Google Maps.
 */
class Polyline
{
    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $length = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $lat += self::readSignedValue($encoded, $index);
            $lng += self::readSignedValue($encoded, $index);
            $points[] = ['lat' => $lat / 100000, 'lng' => $lng / 100000];
        }

        return $points;
    }

    /**
     * @param array<int, array{lat: float, lng: float}> $points
     */
    public static function encode(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point['lat'] * 100000);
            $lng = (int) round($point['lng'] * 100000);

            $encoded .= self::writeSignedValue($lat - $prevLat);
            $encoded .= self::writeSignedValue($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $encoded;
    }

    private static function readSignedValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }

    private static function writeSignedValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $chunk = '';

        while ($value >= 0x20) {
            $chunk .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $chunk . chr($value + 63);
    }
}
