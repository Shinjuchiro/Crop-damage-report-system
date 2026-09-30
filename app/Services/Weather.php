<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Today's weather over Tanza, Cavite.
 *
 * WHY OPEN-METEO
 *
 * No API key. That matters more than it sounds: a key would have to live in
 * Railway's variables, be kept out of the repository, and be rotated if it
 * ever leaked, all for a number on a dashboard. Open-Meteo is free for
 * non-commercial use and asks for nothing, so this service has no secret to
 * manage and no account that can expire the week before a defence.
 *
 * ONE PLACE, ON PURPOSE
 *
 * The coordinates are the municipality's, not the farmer's. This is a
 * municipal system covering one town; a farm in Tres Cruces and one in
 * Daang Amaya share their weather. Asking each phone for its GPS position
 * would be a permission prompt, a privacy question and a different answer
 * per farmer, for a forecast that would read the same.
 *
 * FAILURE IS SILENT
 *
 * Every method returns null rather than throwing. The forecast is a
 * courtesy on a dashboard whose real job is damage reports: if the service
 * is down, or the phone is offline, or the request is slow, the card simply
 * does not appear. A dashboard must never fail to load over the weather.
 */
class Weather
{
    /** Tanza, Cavite. */
    private const LATITUDE  = 14.3947;
    private const LONGITUDE = 120.8517;

    /** Long enough that a busy office is not calling out every page load,
     *  short enough that an afternoon storm is not missed by much. */
    private const CACHE_MINUTES = 30;

    /**
     * @return array{temperature:int, high:int, low:int, description:string, icon:string}|null
     */
    public function today(): ?array
    {
        return Cache::remember('weather.tanza', now()->addMinutes(self::CACHE_MINUTES), function () {
            try {
                $response = Http::timeout(4)->retry(1, 200)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude'  => self::LATITUDE,
                    'longitude' => self::LONGITUDE,
                    'current'   => 'temperature_2m,weather_code',
                    'daily'     => 'temperature_2m_max,temperature_2m_min',
                    'timezone'  => 'Asia/Manila',
                    'forecast_days' => 1,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();

                $code = (int) data_get($data, 'current.weather_code', -1);

                return [
                    'temperature' => (int) round((float) data_get($data, 'current.temperature_2m', 0)),
                    'high'        => (int) round((float) data_get($data, 'daily.temperature_2m_max.0', 0)),
                    'low'         => (int) round((float) data_get($data, 'daily.temperature_2m_min.0', 0)),
                    'description' => $this->describe($code),
                    'icon'        => $this->icon($code),
                ];
            } catch (\Throwable $e) {
                // Logged, not surfaced. A farmer does not need to know the
                // forecast service timed out.
                Log::info('Weather lookup failed: ' . $e->getMessage());

                return null;
            }
        });
    }

    /**
     * WMO weather codes in words.
     *
     * Open-Meteo returns the WMO code table, which is finer grained than a
     * dashboard needs: it separates light, moderate and dense drizzle. These
     * are grouped into what a farmer would actually say, in English with the
     * Filipino after it, because that is how the rest of the app labels
     * things.
     */
    private function describe(int $code): string
    {
        return match (true) {
            $code === 0            => 'Clear / Maaliwalas',
            $code <= 2             => 'Partly Cloudy / Bahagyang maulap',
            $code === 3            => 'Cloudy / Maulap',
            $code >= 45 && $code <= 48  => 'Foggy / Maulap na hamog',
            $code >= 51 && $code <= 57  => 'Drizzle / Ambon',
            $code >= 61 && $code <= 67  => 'Rain / Umuulan',
            $code >= 80 && $code <= 82  => 'Rain Showers / Pag-ulan',
            $code >= 95                 => 'Thunderstorm / Kulog at kidlat',
            default                     => 'Fair / Maayos ang panahon',
        };
    }

    /**
     * Which drawing goes with it. Four are enough: sun, sun behind cloud,
     * cloud, and rain. Anything finer would be a different picture for
     * weather that looks the same out of a window.
     */
    private function icon(int $code): string
    {
        return match (true) {
            $code === 0                 => 'sun',
            $code <= 2                  => 'partly',
            $code === 3                 => 'cloud',
            $code >= 45 && $code <= 48  => 'cloud',
            $code >= 95                 => 'storm',
            default                     => 'rain',
        };
    }
}
