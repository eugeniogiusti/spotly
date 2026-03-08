<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class NominatimService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';

    private function userAgent(): string
    {
        return config('app.name').' contact@'.parse_url(config('app.url'), PHP_URL_HOST);
    }

    /**
     * Forward geocode a query and return matching results with coordinates and bbox.
     * Returns null if the service is unavailable.
     *
     * @return Collection<int, array{display_name: string, lat: float, lon: float, bbox: array{float, float, float, float}}>|null
     */
    public function search(string $query, int $limit = 8): ?Collection
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
                'Accept-Language' => 'en',
            ])
            ->get(self::BASE_URL.'/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => $limit,
                'addressdetails' => 1,
            ]);

        if ($response->failed()) {
            return null;
        }

        return collect($response->json())->map(fn (array $item) => [
            'display_name' => $item['display_name'],
            'lat' => (float) $item['lat'],
            'lon' => (float) $item['lon'],
            'bbox' => [
                // Nominatim returns [south, north, west, east]
                // Normalized to [lat1, lon1, lat2, lon2] (south, west, north, east)
                (float) $item['boundingbox'][0],
                (float) $item['boundingbox'][2],
                (float) $item['boundingbox'][1],
                (float) $item['boundingbox'][3],
            ],
        ]);
    }

    /**
     * Reverse geocode a lat/lng pair and return the city name.
     * Falls back to town → village → municipality → state if city is not available.
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => $this->userAgent()])
            ->get(self::BASE_URL.'/reverse', [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lng,
                'zoom' => 10,
            ]);

        if ($response->failed()) {
            return null;
        }

        $address = $response->json('address', []);

        return $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['state']
            ?? null;
    }
}
