<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Today's weather over Tanza, Cavite, from OpenWeatherMap.
 *
 * It is the service the search engines show for "weather in Tanza", so the
 * dashboard agrees with what anyone would get by checking for themselves.
 * The free plan allows about sixty calls a minute; this makes at most six an
 * hour because of the cache below.
 *
 * The key lives in .env (OPENWEATHER_KEY) and the call is made here, so the
 * key never reaches the browser. The browser asks the /weather route.
 *
 * The coordinates are the municipality's, not the farmer's.
 *
 * Every method returns null rather than throwing, and the card shows a small
 * "Weather unavailable" box on null. A good reading is cached for
 * CACHE_MINUTES and a failure only for RETRY_SECONDS.
 *
 * IF IT RETURNS NULL ON A DEVELOPMENT MACHINE
 *
 * Check storage/logs for 'cURL error 60: unable to get local issuer
 * certificate'. That is PHP on Windows with no certificate bundle: php.ini
 * needs curl.cainfo and openssl.cafile pointing at a cacert.pem. Never turn
 * certificate verification off.
 */
class Weather
{
    private const CACHE_MINUTES = 10;
    private const RETRY_SECONDS = 90;
    private const CACHE_KEY     = 'weather.tanza';
    private const UNAVAILABLE   = 'unavailable';

    private const CURRENT_URL  = 'https://api.openweathermap.org/data/2.5/weather';
    private const FORECAST_URL = 'https://api.openweathermap.org/data/2.5/forecast';

    /**
     * @return array{temperature:int, high:int, low:int, description:string, icon:string}|null
     */
    public function today(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached === self::UNAVAILABLE) {
            return null;
        }

        $reading = $this->fetch();

        Cache::put(
            self::CACHE_KEY,
            $reading ?? self::UNAVAILABLE,
            $reading === null
                ? now()->addSeconds(self::RETRY_SECONDS)
                : now()->addMinutes(self::CACHE_MINUTES)
        );

        return $reading;
    }

    /** Whether a key has been set at all. */
    public function configured(): bool
    {
        return filled(config('services.openweather.key'));
    }

    private function fetch(): ?array
    {
        try {
            $params = [
                'lat'   => config('services.openweather.lat'),
                'lon'   => config('services.openweather.lon'),
                'units' => 'metric',
                'appid' => config('services.openweather.key'),
            ];

            $current = Http::timeout(4)->retry(1, 200)->get(self::CURRENT_URL, $params);

            if (! $current->successful()) {
                // 401 almost always means a key that has not activated yet.
                Log::info('Weather lookup failed: HTTP ' . $current->status() . ' ' . $current->body());

                return null;
            }

            $now  = $current->json();
            $code = (int) data_get($now, 'weather.0.id', 800);

            [$high, $low] = $this->todaysRange($params, $now);

            return [
                'temperature' => (int) round((float) data_get($now, 'main.temp', 0)),
                'high'        => $high,
                'low'         => $low,
                'description' => $this->describe($code),
                'icon'        => $this->icon($code),
            ];
        } catch (\Throwable $e) {
            Log::info('Weather lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Today's high and low, from the five-day forecast (the current endpoint's
     * temp_min/temp_max are only the spread across the area right now). Falls
     * back to the current endpoint's figures if the forecast call fails.
     *
     * @return array{0:int, 1:int}
     */
    private function todaysRange(array $params, array $now): array
    {
        $fallback = [
            (int) round((float) data_get($now, 'main.temp_max', data_get($now, 'main.temp', 0))),
            (int) round((float) data_get($now, 'main.temp_min', data_get($now, 'main.temp', 0))),
        ];

        try {
            $forecast = Http::timeout(4)->get(self::FORECAST_URL, $params);

            if (! $forecast->successful()) {
                return $fallback;
            }

            $today = now()->setTimezone('Asia/Manila')->toDateString();

            $temps = collect(data_get($forecast->json(), 'list', []))
                ->filter(fn ($slot) => str_starts_with((string) data_get($slot, 'dt_txt'), $today))
                ->map(fn ($slot) => (float) data_get($slot, 'main.temp'))
                ->push((float) data_get($now, 'main.temp', 0))
                ->filter();

            if ($temps->isEmpty()) {
                return $fallback;
            }

            return [(int) round($temps->max()), (int) round($temps->min())];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /** OpenWeatherMap condition codes in words, English with the Filipino after it. */
    private function describe(int $code): string
    {
        return match (true) {
            $code >= 200 && $code < 300    => 'Thunderstorm / Kulog at kidlat',
            $code >= 300 && $code < 400    => 'Drizzle / Ambon',
            $code >= 500 && $code < 520    => 'Rain / Umuulan',
            $code >= 520 && $code < 600    => 'Rain Showers / Pag-ulan',
            $code >= 700 && $code < 800    => 'Hazy / Maulap na hamog',
            $code === 800                  => 'Clear / Maaliwalas',
            $code === 801 || $code === 802 => 'Partly Cloudy / Bahagyang maulap',
            $code === 803 || $code === 804 => 'Cloudy / Maulap',
            default                        => 'Fair / Maayos ang panahon',
        };
    }

    /** Five drawings: sun, sun behind cloud, cloud, rain, storm. */
    private function icon(int $code): string
    {
        return match (true) {
            $code >= 200 && $code < 300    => 'storm',
            $code >= 300 && $code < 700    => 'rain',
            $code >= 700 && $code < 800    => 'cloud',
            $code === 800                  => 'sun',
            $code === 801 || $code === 802 => 'partly',
            default                        => 'cloud',
        };
    }
}
