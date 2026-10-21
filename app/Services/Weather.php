<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Today's weather over Tanza, Cavite.
 *
 * WHY OPENWEATHERMAP
 *
 * Because it is what the search engines are already showing. Search for
 * "weather in Tanza" and the card that comes back says, in small print at
 * the bottom, "Data from OpenWeatherMap" - so reading the same service is
 * how this dashboard agrees with the number anyone would get by checking
 * for themselves. A different service is not wrong, it is a different
 * model, and "the system says 26 but Google says 28" is not a conversation
 * worth having at a defence.
 *
 * The free plan allows about sixty calls a minute. This makes at most six
 * an hour, because of the cache below.
 *
 * WHY THE KEY IS IN .env AND THE CALL IS MADE HERE
 *
 * The browser could call OpenWeatherMap directly and skip this class
 * entirely, which would be simpler. It would also put the API key in the
 * page source for anyone to read. So the key stays in .env, PHP makes the
 * call, and the browser asks this application instead - see the /weather
 * route and components/ui/weather.blade.php.
 *
 * ONE PLACE, ON PURPOSE
 *
 * The coordinates are the municipality's, not the farmer's. Asking each
 * phone for its GPS position would be a permission prompt, a privacy
 * question and a different answer per farmer, for a forecast that reads the
 * same across one town.
 *
 * FAILURE IS SILENT
 *
 * Every method returns null rather than throwing, and the card renders
 * nothing when it gets null. No key, no network, a rate limit, an outage:
 * the card is simply not there. A dashboard whose job is damage reports
 * must never fail to load over the weather.
 *
 * A FAILURE IS NOT CACHED LIKE AN ANSWER
 *
 * A good reading is kept for CACHE_MINUTES and a failure only for
 * RETRY_SECONDS, so one bad moment does not hide the card long after it has
 * passed. This used to be a single Cache::remember for both, and null is a
 * value as far as the cache is concerned, so one failed lookup hid the card
 * for half an hour.
 *
 * IF IT RETURNS NULL ON A DEVELOPMENT MACHINE
 *
 * Check storage/logs for 'cURL error 60: unable to get local issuer
 * certificate'. That is PHP on Windows with no certificate authority bundle
 * configured: php.ini needs curl.cainfo and openssl.cafile pointing at a
 * cacert.pem. It is not a fault in this class, it breaks the SMS gateway in
 * exactly the same way, and the answer is never to turn certificate
 * verification off.
 */
class Weather
{
    /** How long a good reading is kept. OpenWeatherMap updates on roughly a
     *  ten minute cycle, so asking more often would be a slower page showing
     *  the same number. */
    private const CACHE_MINUTES = 10;

    /** How long a FAILURE is remembered. */
    private const RETRY_SECONDS = 90;

    private const CACHE_KEY = 'weather.tanza';

    /** Stored in place of a reading when the lookup failed. A plain null
     *  cannot be told apart from "nothing cached yet". */
    private const UNAVAILABLE = 'unavailable';

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

    /**
     * One pass at the service, or null if anything at all goes wrong.
     *
     * @return array{temperature:int, high:int, low:int, description:string, icon:string}|null
     */
    private function fetch(): ?array
    {
        try {
            $params = [
                'lat'   => config('services.openweather.lat'),
                'lon'   => config('services.openweather.lon'),
                'units' => 'metric',          // Celsius, as the Philippines reads it
                'appid' => config('services.openweather.key'),
            ];

            $current = Http::timeout(4)->retry(1, 200)->get(self::CURRENT_URL, $params);

            if (! $current->successful()) {
                // 401 almost always means a key that has not activated yet;
                // a new one takes a couple of hours.
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
            // Logged, not surfaced. A farmer does not need to know the
            // forecast service timed out.
            Log::info('Weather lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Today's high and low.
     *
     * The current-conditions endpoint carries main.temp_min and
     * main.temp_max, but on that endpoint they are the spread across the
     * reporting area at this moment rather than the day's range, which is
     * why they are so often the same number as the temperature itself. The
     * day's real range comes from the five-day forecast, which is
     * three-hourly: take every slot that falls on today in Manila time and
     * read off the extremes.
     *
     * If that second call fails the reading is still worth showing, so this
     * falls back to the current endpoint's own figures rather than throwing
     * the whole thing away.
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
                // Late in the evening there may be no slots left for today,
                // and the reading now is still part of today's range.
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

    /**
     * OpenWeatherMap condition codes in words.
     *
     * The code table is finer grained than a dashboard needs - it separates
     * light, moderate and heavy rain, and names nine kinds of haze - so
     * these are grouped into what a farmer would actually say, in English
     * with the Filipino after it, because that is how the rest of the app
     * labels things.
     */
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

    /**
     * Which drawing goes with it. Five are enough: sun, sun behind cloud,
     * cloud, rain and storm. Anything finer would be a different picture
     * for weather that looks the same out of a window.
     */
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
