<?php

namespace App\Http\Controllers;

use App\Models\SavedPoi;
use App\Queries\PoiTags\PoiTagCountsQuery;
use App\Queries\PoiTags\PoiTagUserTagsQuery;
use App\Queries\SavedPois\SavedPoiCitiesQuery;
use App\Queries\SavedPois\SavedPoiIndexQuery;
use App\Services\SavedPoiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the My Places page — a paginated, filterable list of the user's saved POIs.
 */
class SavedPlacesController extends Controller
{
    public function __construct(private readonly SavedPoiService $savedPois) {}

    /**
     * Display saved POIs with city/layer/search filters, tag counts, and user tags.
     * Uses infinite scroll via Inertia::scroll().
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $layers = collect(config('layers'))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'color' => $config['color'],
            ]);

        $tagKeys = ['phone', 'website', 'opening_hours', 'cuisine', 'addr:street', 'addr:housenumber', 'addr:city'];

        $cities = (new SavedPoiCitiesQuery($userId))->handle();
        $totalCount = $this->savedPois->getTotalCount($userId);
        $paginator = (new SavedPoiIndexQuery($userId))->handle();

        $externalIds = $paginator->getCollection()->pluck('poi_external_id')->all();
        $tagCountsByPoi = (new PoiTagCountsQuery($externalIds))->handle();
        $userTagsByPoi = (new PoiTagUserTagsQuery($externalIds, $userId))->handle();

        $paginator->through(function (SavedPoi $savedPoi) use ($tagKeys, $tagCountsByPoi, $userTagsByPoi) {
            $tags = $savedPoi->poi?->raw_data['tags'] ?? [];
            $savedPoi->details = collect($tagKeys)
                ->mapWithKeys(fn (string $key) => [$key => $tags[$key] ?? null])
                ->filter()
                ->all();

            $savedPoi->community_tags = [
                'counts' => ($tagCountsByPoi[$savedPoi->poi_external_id] ?? collect())->pluck('total', 'tag'),
                'user_tags' => ($userTagsByPoi[$savedPoi->poi_external_id] ?? collect())->pluck('tag')->values()->all(),
            ];

            return $savedPoi;
        });

        return Inertia::render('MyPlaces', [
            'pois' => Inertia::scroll($paginator),
            'layers' => $layers,
            'cities' => $cities,
            'totalCount' => $totalCount,
            'selectedCity' => request('city'),
            'selectedLayer' => request('layer'),
            'search' => request('search', ''),
        ]);
    }
}
