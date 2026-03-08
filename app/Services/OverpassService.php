<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OverpassService
{
    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    private const TIMEOUT_SECONDS = 30;

    /**
     * Fetch POIs from Overpass API for a given bounding box and layer.
     *
     * @param  string  $bbox  "lat1,lon1,lat2,lon2" (south, west, north, east)
     * @param  string  $layer  Layer key from config/layers.php (e.g. "food")
     * @return array<int, array{external_id: string, source: string, layer: string, name: string, lat: float, lng: float, raw_data: array}>
     */
    public function fetch(string $bbox, string $layer): array
    {
        $tags = $this->tagsForLayer($layer);

        $query = $this->buildQuery($bbox, $tags);

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->asForm()
            ->post(self::OVERPASS_URL, ['data' => $query]);

        if ($response->failed()) {
            throw new RuntimeException("Overpass API error: HTTP {$response->status()}");
        }

        $elements = $response->json('elements', []);

        return array_values(
            array_filter(
                array_map(fn (array $element) => $this->normalize($element, $layer), $elements),
                fn (array $poi) => $this->hasMinimumData($poi),
            )
        );
    }

    /**
     * Build an OverpassQL query for the given bbox and tag list.
     *
     * @param  array<int, array<string, string>>  $tags
     */
    private function buildQuery(string $bbox, array $tags): string
    {
        $lines = [];

        foreach ($tags as $tag) {
            foreach ($tag as $key => $value) {
                // Query nodes and ways — no ["name"] filter, we handle fallback in normalize
                $lines[] = "  node[\"{$key}\"=\"{$value}\"]({$bbox});";
                $lines[] = "  way[\"{$key}\"=\"{$value}\"]({$bbox});";
            }
        }

        $body = implode("\n", $lines);

        return "[out:json][timeout:25];\n(\n{$body}\n);\nout center;";
    }

    /**
     * Normalize a raw Overpass element into our standard POI format.
     *
     * @param  array<string, mixed>  $element
     * @return array{external_id: string, source: string, layer: string, name: string, lat: float, lng: float, raw_data: array}
     */
    private function normalize(array $element, string $layer): array
    {
        $type = $element['type'] ?? 'node';
        $id = $element['id'] ?? 0;
        $tags = $element['tags'] ?? [];

        // Ways return a "center" object instead of direct lat/lon
        $lat = (float) ($element['lat'] ?? $element['center']['lat'] ?? 0);
        $lng = (float) ($element['lon'] ?? $element['center']['lon'] ?? 0);

        $name = $tags['name']
            ?? $tags['brand']
            ?? $tags['operator']
            ?? $tags['ref']
            ?? null;

        return [
            'external_id' => "osm:{$type}:{$id}",
            'source' => 'overpass',
            'layer' => $layer,
            'name' => $name ?? '',
            'lat' => $lat,
            'lng' => $lng,
            'raw_data' => $element,
        ];
    }

    /**
     * Check that a normalized POI has at minimum a name and valid coordinates.
     *
     * @param  array{raw_data: array}  $poi
     */
    private function hasMinimumData(array $poi): bool
    {
        return ! empty($poi['name']) && $poi['lat'] !== 0.0 && $poi['lng'] !== 0.0;
    }

    /**
     * Get the OSM tags for a layer from config/layers.php.
     *
     * @return array<int, array<string, string>>
     */
    private function tagsForLayer(string $layer): array
    {
        $config = config("layers.{$layer}");

        if (! $config) {
            throw new RuntimeException("Unknown layer: {$layer}");
        }

        return $config['tags'];
    }
}
