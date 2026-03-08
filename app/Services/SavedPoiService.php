<?php

namespace App\Services;

use App\Models\SavedPoi;

/**
 * Handles all mutations and simple reads for a user's saved POIs.
 * Reverse geocoding via NominatimService is performed at save time to store the city.
 */
class SavedPoiService
{
    public function __construct(private readonly NominatimService $nominatim) {}

    /**
     * Save a POI for a user, resolving the city via reverse geocoding.
     *
     * @param  array{poi_external_id: string, layer: string, name: string, lat: float, lng: float}  $data
     */
    public function store(int $userId, array $data): SavedPoi
    {
        $city = $this->nominatim->reverseGeocode((float) $data['lat'], (float) $data['lng']);

        return SavedPoi::firstOrCreate(
            [
                'user_id' => $userId,
                'poi_external_id' => $data['poi_external_id'],
            ],
            [
                'layer' => $data['layer'],
                'name' => $data['name'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'city' => $city,
            ]
        );
    }

    /**
     * Update the personal notes on a saved POI. Passing null clears the notes.
     */
    public function updateNotes(int $userId, string $externalId, ?string $notes): void
    {
        SavedPoi::query()
            ->where('user_id', $userId)
            ->where('poi_external_id', $externalId)
            ->update(['notes' => $notes]);
    }

    /**
     * Delete a saved POI for the given user.
     */
    public function destroy(int $userId, string $externalId): void
    {
        SavedPoi::query()
            ->where('user_id', $userId)
            ->where('poi_external_id', $externalId)
            ->delete();
    }

    /**
     * Return the total number of POIs saved by the user.
     */
    public function getTotalCount(int $userId): int
    {
        return SavedPoi::query()
            ->where('user_id', $userId)
            ->count();
    }

    /** @return string[] */
    public function getSavedPoiIds(int $userId): array
    {
        return SavedPoi::query()
            ->where('user_id', $userId)
            ->pluck('poi_external_id')
            ->all();
    }
}
