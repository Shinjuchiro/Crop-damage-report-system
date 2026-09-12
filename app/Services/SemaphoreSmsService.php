<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a text message through Semaphore (semaphore.co), a Philippine SMS
 * gateway billed in pesos - a better fit for this Municipal Agriculture
 * Office's procurement than a USD-billed international provider such as
 * Twilio, and simple enough (one REST endpoint, form-encoded POST) to keep
 * within the "practical, not an enterprise platform" scope of the
 * proposal (section 2).
 *
 * Credentials come from config/services.php, which reads them from .env
 * (SEMAPHORE_API_KEY, SEMAPHORE_SENDER_NAME). Nothing here is ever called
 * with the key hardcoded, and nothing here is called unless the key is
 * set - configured() below is what the rest of the system checks first,
 * so the alerts screen keeps working normally (in-app only) before the
 * office finishes setting up its Semaphore account.
 */
class SemaphoreSmsService
{
    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    public function configured(): bool
    {
        return filled(config('services.semaphore.api_key'));
    }

    /**
     * @return array{success: bool, error: ?string}
     */
    public function send(string $phoneNumber, string $message): array
    {
        if (! $this->configured()) {
            return ['success' => false, 'error' => 'SMS provider is not configured.'];
        }

        $phoneNumber = $this->normalize($phoneNumber);

        if (! $phoneNumber) {
            return ['success' => false, 'error' => 'No valid phone number on file.'];
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::ENDPOINT, [
                'apikey'     => config('services.semaphore.api_key'),
                'number'     => $phoneNumber,
                'message'    => $message,
                'sendername' => config('services.semaphore.sender_name'),
            ]);

            if ($response->successful()) {
                return ['success' => true, 'error' => null];
            }

            Log::warning('Semaphore SMS send rejected', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['success' => false, 'error' => 'Provider responded with status ' . $response->status()];
        } catch (\Throwable $e) {
            Log::warning('Semaphore SMS send failed: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Could not reach the SMS provider.'];
        }
    }

    /**
     * Semaphore expects a PH mobile number such as 09171234567 or
     * 639171234567. This only strips anything that is not a digit - it
     * does not try to fully validate a PH number, and leaves Semaphore to
     * reject anything still malformed.
     */
    private function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return $digits !== '' ? $digits : null;
    }
}
