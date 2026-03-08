<?php

namespace App\Queries\PoiTags;

use App\Models\PoiTag;
use Illuminate\Support\Collection;

/**
 * Returns the tags cast by a specific user on one or more POIs.
 * Returns a Collection grouped by poi_external_id for efficient lookup.
 * Pass a single-element array when querying one POI.
 */
class PoiTagUserTagsQuery
{
    /**
     * @param  array<int, string>  $externalIds
     */
    public function __construct(
        private readonly array $externalIds,
        private readonly int $userId,
    ) {}

    /**
     * Returns user tags grouped by poi_external_id.
     *
     * @return Collection<string, Collection>
     */
    public function handle(): Collection
    {
        return PoiTag::query()
            ->whereIn('poi_external_id', $this->externalIds)
            ->where('user_id', $this->userId)
            ->get(['poi_external_id', 'tag'])
            ->groupBy('poi_external_id');
    }
}
