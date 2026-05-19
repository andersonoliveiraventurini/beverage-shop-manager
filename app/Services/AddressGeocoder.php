<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nominatim (OpenStreetMap) geocoder.
 *
 * Hits https://nominatim.openstreetmap.org/search with a 1 req/s polite limit,
 * caches every translated address for 30 days, and identifies itself with a
 * unique User-Agent (Nominatim's usage policy). Returns null on any failure
 * mode — the caller decides whether to fall back to manual coordinates.
 *
 * The HTTP layer is wrapped in Http facade calls so tests can fake the API.
 */
class AddressGeocoder
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    private const USER_AGENT = 'FA-Distribuidora-System/1.0 (contact: anderson.oliveira.venturini@gmail.com)';

    /**
     * Translate the address to lat/lng. Returns null when the address is
     * incomplete or Nominatim returns no result.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function lookup(CustomerAddress $address): ?array
    {
        $query = $this->buildQuery($address);
        if ($query === null) {
            return null;
        }

        return Cache::remember(
            'geocode:' . sha1($query),
            now()->addDays(30),
            function () use ($query) {
                try {
                    $response = Http::withUserAgent(self::USER_AGENT)
                        ->acceptJson()
                        ->timeout(10)
                        ->get(self::BASE_URL, [
                            'q' => $query,
                            'format' => 'json',
                            'limit' => 1,
                            'countrycodes' => 'br',
                        ]);

                    if (! $response->successful()) {
                        return null;
                    }

                    $body = $response->json();
                    if (! is_array($body) || empty($body[0]['lat']) || empty($body[0]['lon'])) {
                        return null;
                    }

                    return [
                        'lat' => (float) $body[0]['lat'],
                        'lng' => (float) $body[0]['lon'],
                    ];
                } catch (\Throwable $e) {
                    Log::warning('Nominatim geocoder failed', [
                        'query' => $query,
                        'message' => $e->getMessage(),
                    ]);
                    return null;
                }
            },
        );
    }

    private function buildQuery(CustomerAddress $address): ?string
    {
        $parts = array_filter([
            trim((string) $address->street),
            trim((string) $address->number),
            trim((string) $address->district),
            trim((string) $address->city),
            trim((string) $address->state),
        ]);

        if (count($parts) < 3) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * Apply lookup() to the address and persist the lat/lng if the lookup
     * succeeds. Returns true on success, false on miss / when coords already
     * exist (no overwrite of manually entered coords).
     */
    public function fill(CustomerAddress $address, bool $overwriteExisting = false): bool
    {
        if (! $overwriteExisting && $address->lat !== null && $address->lng !== null) {
            return false;
        }

        $coords = $this->lookup($address);
        if ($coords === null) {
            return false;
        }

        $address->lat = $coords['lat'];
        $address->lng = $coords['lng'];
        $address->save();

        return true;
    }
}
