<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class SppdExchangeRate
{
    public const SOURCE = 'https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html';

    private const FEED = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    public function latest(): array
    {
        return Cache::remember('sppd:ecb-usd-idr', 3600, function (): array {
            $rates = [];
            $date = '';
            try {
                $body = Http::timeout(4)->get(self::FEED)->throw()->body();
                $xml = @simplexml_load_string($body, options: LIBXML_NONET);
                $day = $xml ? ($xml->xpath('//*[local-name()="Cube" and @time]')[0] ?? null) : null;
                $date = (string) ($day['time'] ?? '');
                foreach ($day?->xpath('./*[local-name()="Cube"]') ?: [] as $entry) {
                    $rates[(string) $entry['currency']] = (float) $entry['rate'];
                }
            } catch (\Illuminate\Http\Client\HttpClientException) {
                // A temporary failure remains retryable and is not cached.
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || ($rates['USD'] ?? 0) <= 0 || ($rates['IDR'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['exchange_rate' => 'Kurs acuan USD belum tersedia. Coba lagi atau isi anggaran dalam rupiah.']);
            }

            return ['idr_per_usd' => round($rates['IDR'] / $rates['USD'], 2), 'date' => $date];
        });
    }
}
