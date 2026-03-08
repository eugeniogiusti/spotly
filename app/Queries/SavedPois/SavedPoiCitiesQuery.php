<?php

namespace App\Queries\SavedPois;

use App\Models\SavedPoi;
use Illuminate\Support\Collection;

/**
 * Returns the distinct cities where a user has saved POIs, sorted alphabetically.
 * Used to populate the city filter dropdown in My Places.
 */
class SavedPoiCitiesQuery
{
    public function __construct(private readonly int $userId) {}

    public function handle(): Collection
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }
}
