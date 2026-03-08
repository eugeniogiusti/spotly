<?php

namespace App\Http\Controllers;

use App\Services\SavedPoiService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function __construct(private readonly SavedPoiService $savedPois) {}

    /**
     * Render the public map page, passing the layer definitions to the frontend.
     */
    public function index(): Response
    {
        $layers = collect(config('layers'))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'color' => $config['color'],
            ])
            ->values();

        $savedPoiIds = Auth::check()
            ? $this->savedPois->getSavedPoiIds(Auth::id())
            : [];

        return Inertia::render('Map', [
            'layers' => $layers,
            'savedPoiIds' => $savedPoiIds,
        ]);
    }
}
