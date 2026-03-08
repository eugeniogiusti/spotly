<?php

namespace App\Queries\SavedPois;

use App\Models\SavedPoi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated list of a user's saved POIs.
 * Filters: city, layer, search (name or city). Sorted by city → layer → name.
 * Reads filter values directly from the current request.
 */
class SavedPoiIndexQuery
{
    public function __construct(private readonly int $userId) {}

    /**
     * Execute the query and return a paginated result (20 per page).
     * Filters are read from the current request: city, layer, search.
     */
    public function handle(): LengthAwarePaginator
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->when(request('city'), fn ($q, $city) => $q->where('city', $city))
            ->when(request('layer'), fn ($q, $layer) => $q->where('layer', $layer))
            ->when(request('search'), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            }))
            ->with('poi:id,external_id,raw_data')
            ->orderBy('city')
            ->orderBy('layer')
            ->orderBy('name')
            ->paginate(20);
    }
}
