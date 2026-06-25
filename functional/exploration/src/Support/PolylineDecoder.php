<?php

namespace Functional\Exploration\Support;

class PolylineDecoder
{
    /**
     * Decode a Google encoded polyline string into latitude and longitude pairs.
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public function decode(string $polyline, int $precision = 5): array
    {
        $factor = 10 ** $precision;
        $length = strlen($polyline);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $points = [];

        while ($index < $length) {
            $lat += $this->decodeValue($polyline, $index);
            $lng += $this->decodeValue($polyline, $index);

            $points[] = [
                'lat' => $lat / $factor,
                'lng' => $lng / $factor,
            ];
        }

        return $points;
    }

    /**
     * Decode a single signed value from the polyline advancing the index.
     */
    private function decodeValue(string $polyline, int &$index): int
    {
        $shift = 0;
        $result = 0;

        do {
            $byte = ord($polyline[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);
    }
}
