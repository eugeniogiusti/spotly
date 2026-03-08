<?php

namespace App\Queries\PoiTags;

use App\Models\PoiTag;
use Illuminate\Support\Collection;

/**
 * Aggregates community tag counts for one or more POIs.
 * Returns a Collection grouped by poi_external_id for efficient lookup.
 * Pass a single-element array when querying one POI.
 */
class PoiTagCountsQuery
{
    /** @param array<int, string> $externalIds */
    public function __construct(private readonly array $externalIds) {}

    /**
     * Returns counts grouped by poi_external_id.
     * Each group contains items with 'tag' and 'total'.
     *
     * @return Collection<string, Collection>
     */
    public function handle(): Collection
    {
        return PoiTag::query()
            ->whereIn('poi_external_id', $this->externalIds)
            ->selectRaw('poi_external_id, tag, count(*) as total')
            ->groupBy('poi_external_id', 'tag')
            ->get()
            ->groupBy('poi_external_id');
    }
}
