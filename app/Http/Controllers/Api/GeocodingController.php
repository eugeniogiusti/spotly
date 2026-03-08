<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GeocodingSearchRequest;
use App\Services\NominatimService;
use Illuminate\Http\JsonResponse;

class GeocodingController extends Controller
{
    public function __construct(private readonly NominatimService $nominatim) {}

    /**
     * Search for a location by name and return coordinates + bounding box.
     * Proxied through Laravel to comply with Nominatim's User-Agent policy.
     */
    public function search(GeocodingSearchRequest $request): JsonResponse
    {
        $results = $this->nominatim->search($request->validated('q'));

        if ($results === null) {
            return response()->json(['error' => 'Geocoding service unavailable.'], 503);
        }

        return response()->json($results);
    }
}
