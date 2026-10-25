<?php

namespace App\Support;

/**
 * Is a point inside the municipality of Tanza?
 *
 * Reads public/geo/tanza-outline.json, the union of the 41 barangay polygons
 * (the same file the maps use to hide everything that is not Tanza), and does
 * a plain point-in-polygon test. The technician's verified location has to
 * pass it, so the browser's check is never the only one.
 *
 * If the file is missing or unreadable this answers true: a broken data file
 * must not stop a technician from filing an inspection in the field.
 */
class TanzaBounds
{
    private static ?array $ring = null;
    private static bool $loaded = false;

    public static function contains(float $lat, float $lng): bool
    {
        $ring = self::ring();

        if ($ring === null) {
            return true;
        }

        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];   // [lng, lat]
            [$xj, $yj] = $ring[$j];

            if ((($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function ring(): ?array
    {
        if (self::$loaded) {
            return self::$ring;
        }

        self::$loaded = true;

        $path = public_path('geo/tanza-outline.json');

        if (! is_file($path)) {
            return null;
        }

        $geo = json_decode((string) file_get_contents($path), true);

        return self::$ring = $geo['features'][0]['geometry']['coordinates'][0] ?? null;
    }
}
