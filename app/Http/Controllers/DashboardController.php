<?php

namespace App\Http\Controllers;

use App\Queries\SavedPois\DashboardQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the authenticated user's dashboard with saved POI stats and recent activity.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard — delegates all data aggregation to DashboardQuery.
     */
    public function index(Request $request): Response
    {
        $data = (new DashboardQuery($request->user()->id))->handle();

        $layers = collect(config('layers'))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'color' => $config['color'],
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalSaved' => $data['totalSaved'],
                'citiesCount' => $data['citiesCount'],
                'favoriteLayer' => $data['favoriteLayer'],
            ],
            'cities' => $data['cities'],
            'recentPlaces' => $data['recentPlaces'],
            'layers' => $layers,
        ]);
    }
}
