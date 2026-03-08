<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PoisRequest;
use App\Services\PoiCacheService;
use Illuminate\Http\JsonResponse;

class PoisController extends Controller
{
    public function __construct(private readonly PoiCacheService $pois) {}

    /**
     * Return POIs for the given bounding box and layer.
     * Served from DB cache if fresh, otherwise fetched live from Overpass.
     */
    public function index(PoisRequest $request): JsonResponse
    {
        try {
            $pois = $this->pois->get(
                bbox: $request->validated('bbox'),
                layer: $request->validated('layer'),
            );

            return response()->json($pois->values());
        } catch (\RuntimeException $e) {
            return response()->json([], 503);
        }
    }
}
