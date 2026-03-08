<?php

namespace App\Http\Controllers\Api;

use App\Enums\PoiTagEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TogglePoiTagRequest;
use App\Queries\PoiTags\PoiTagCountsQuery;
use App\Queries\PoiTags\PoiTagUserTagsQuery;
use App\Services\PoiTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages community tags on POIs (read counts + toggle).
 */
class PoiTagsController extends Controller
{
    public function __construct(private readonly PoiTagService $poiTagService) {}

    /**
     * Return tag counts and the authenticated user's own tags for a POI.
     *
     * @return JsonResponse{counts: array<string, int>, user_tags: string[]}
     */
    public function index(Request $request, string $externalId): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $counts = (new PoiTagCountsQuery([$externalId]))->handle()
                ->get($externalId, collect())
                ->pluck('total', 'tag');

            $userTags = (new PoiTagUserTagsQuery([$externalId], $userId))->handle()
                ->get($externalId, collect())
                ->pluck('tag');

            return response()->json([
                'counts' => $counts,
                'user_tags' => $userTags,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['counts' => [], 'user_tags' => [], 'message' => __('ui.error_tag')], 500);
        }
    }

    /**
     * Toggle a community tag on a POI for the authenticated user.
     *
     * @return JsonResponse{tag: string, added: bool, count: int}
     */
    public function toggle(TogglePoiTagRequest $request, string $externalId): JsonResponse
    {
        try {
            $result = $this->poiTagService->toggle(
                externalId: $externalId,
                userId: $request->user()->id,
                tag: PoiTagEnum::from($request->validated('tag')),
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('ui.error_tag')], 500);
        }
    }
}
