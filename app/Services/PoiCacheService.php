<?php

namespace App\Services;

use App\Models\Poi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PoiCacheService
{
    /**
     * How many hours before cached POIs are considered stale.
     */
    private const TTL_HOURS = 24;

    public function __construct(private readonly OverpassService $overpass) {}

    /**
     * Return POIs for the given bbox and layer.
     * Serves from DB cache if the exact bbox (or a superset) was queried recently;
     * otherwise fetches from Overpass and stores results.
     *
     * @param  string  $bbox  "lat1,lon1,lat2,lon2" (south, west, north, east)
     * @param  string  $layer  Layer key from config/layers.php (e.g. "food")
     * @return Collection<int, Poi>
     */
    public function get(string $bbox, string $layer): Collection
    {
        [$south, $west, $north, $east] = $this->parseBbox($bbox);

        if ($this->bboxWasQueried($south, $west, $north, $east, $layer)) {
            return $this->fromCache($south, $west, $north, $east, $layer);
        }

        try {
            $pois = $this->overpass->fetch($bbox, $layer);
            $this->store($pois, $layer);
            $this->recordQuery($south, $west, $north, $east, $layer);
        } catch (\RuntimeException $e) {
            // Overpass failed — serve stale cache if available rather than returning nothing
            $stale = $this->fromCache($south, $west, $north, $east, $layer);
            if ($stale->isNotEmpty()) {
                return $stale;
            }

            throw $e;
        }

        return $this->fromCache($south, $west, $north, $east, $layer);
    }

    /**
     * Check if a previous query fully covered the requested bbox for this layer.
     * A previous query covers the current bbox if its boundaries are a superset.
     */
    private function bboxWasQueried(float $south, float $west, float $north, float $east, string $layer): bool
    {
        return DB::table('poi_queries')
            ->where('layer', $layer)
            ->where('south', '<=', $south)
            ->where('west', '<=', $west)
            ->where('north', '>=', $north)
            ->where('east', '>=', $east)
            ->where('queried_at', '>=', Carbon::now()->subHours(self::TTL_HOURS))
            ->exists();
    }

    /**
     * Record that this bbox+layer was fully queried from Overpass.
     */
    private function recordQuery(float $south, float $west, float $north, float $east, string $layer): void
    {
        DB::table('poi_queries')->insert([
            'layer' => $layer,
            'south' => $south,
            'west' => $west,
            'north' => $north,
            'east' => $east,
            'queried_at' => Carbon::now(),
        ]);
    }

    /**
     * Query cached POIs from DB within the given bbox and layer.
     *
     * @return Collection<int, Poi>
     */
    private function fromCache(float $south, float $west, float $north, float $east, string $layer): Collection
    {
        return Poi::query()
            ->where('layer', $layer)
            ->whereBetween('lat', [$south, $north])
            ->whereBetween('lng', [$west, $east])
            ->get();
    }

    /**
     * Upsert a list of normalized POIs into the DB, setting cached_at to now.
     *
     * @param  array<int, array{external_id: string, source: string, layer: string, name: string, lat: float, lng: float, raw_data: array}>  $pois
     */
    private function store(array $pois, string $layer): void
    {
        $now = Carbon::now();

        foreach ($pois as $poi) {
            Poi::updateOrCreate(
                ['external_id' => $poi['external_id']],
                [
                    'source' => $poi['source'],
                    'layer' => $layer,
                    'name' => $poi['name'],
                    'lat' => $poi['lat'],
                    'lng' => $poi['lng'],
                    'raw_data' => $poi['raw_data'],
                    'cached_at' => $now,
                ]
            );
        }
    }

    /**
     * Parse a "lat1,lon1,lat2,lon2" string into an array of floats.
     *
     * @return array{float, float, float, float}
     */
    private function parseBbox(string $bbox): array
    {
        $parts = array_map('floatval', explode(',', $bbox));

        return [$parts[0], $parts[1], $parts[2], $parts[3]];
    }
}
