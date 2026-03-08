<?php

namespace App\Queries\SavedPois;

use App\Models\SavedPoi;
use Illuminate\Support\Collection;

/**
 * Aggregates all data needed for the Dashboard page in a single query class.
 */
class DashboardQuery
{
    public function __construct(private readonly int $userId) {}

    /**
     * @return array{totalSaved: int, citiesCount: int, favoriteLayer: string|null, cities: Collection, recentPlaces: Collection}
     */
    public function handle(): array
    {
        return [
            'totalSaved' => $this->getTotalSaved(),
            'citiesCount' => $this->getCitiesCount(),
            'favoriteLayer' => $this->getFavoriteLayer(),
            'cities' => $this->getCities(),
            'recentPlaces' => $this->getRecentPlaces(),
        ];
    }

    private function getTotalSaved(): int
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->count();
    }

    private function getCitiesCount(): int
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->whereNotNull('city')
            ->distinct('city')
            ->count('city');
    }

    private function getFavoriteLayer(): ?string
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->selectRaw('layer, count(*) as total')
            ->groupBy('layer')
            ->orderByDesc('total')
            ->value('layer');
    }

    /** @return Collection<int, array{city: string, count: int, layers: string[]}> */
    private function getCities(): Collection
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->whereNotNull('city')
            ->get(['city', 'layer'])
            ->groupBy('city')
            ->map(fn ($pois, string $city) => [
                'city' => $city,
                'count' => $pois->count(),
                'layers' => $pois->pluck('layer')->unique()->values()->all(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();
    }

    private function getRecentPlaces(): Collection
    {
        return SavedPoi::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'name', 'layer', 'city', 'lat', 'lng', 'created_at']);
    }
}
