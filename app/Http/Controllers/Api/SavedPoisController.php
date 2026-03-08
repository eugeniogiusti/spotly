<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSavedPoiRequest;
use App\Http\Requests\Api\UpdateSavedPoiNotesRequest;
use App\Services\SavedPoiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API endpoints for saving and managing a user's saved POIs.
 */
class SavedPoisController extends Controller
{
    public function __construct(private readonly SavedPoiService $savedPois) {}

    /**
     * Save a POI for the authenticated user (idempotent — returns 200 if already saved).
     */
    public function store(StoreSavedPoiRequest $request): JsonResponse
    {
        $saved = $this->savedPois->store($request->user()->id, $request->validated());

        return response()->json($saved, $saved->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update the personal notes for a saved POI.
     */
    public function updateNotes(UpdateSavedPoiNotesRequest $request, string $externalId): JsonResponse
    {
        $this->savedPois->updateNotes($request->user()->id, $externalId, $request->validated('notes'));

        return response()->json(['ok' => true]);
    }

    /**
     * Remove a POI from the user's saved list.
     */
    public function destroy(Request $request, string $externalId): Response
    {
        $this->savedPois->destroy($request->user()->id, $externalId);

        return response()->noContent();
    }
}
