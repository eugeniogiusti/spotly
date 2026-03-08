<?php

namespace App\Services;

use App\Enums\PoiTagEnum;
use App\Models\PoiTag;

/**
 * Handles community tag mutations on POIs (toggle add/remove).
 */
class PoiTagService
{
    /**
     * Toggle a tag on a POI for the given user.
     * Returns whether the tag was added and the new total count.
     *
     * @return array{tag: string, added: bool, count: int}
     */
    public function toggle(string $externalId, int $userId, PoiTagEnum $tag): array
    {
        $existing = PoiTag::query()
            ->where('poi_external_id', $externalId)
            ->where('user_id', $userId)
            ->where('tag', $tag->value)
            ->first();

        if ($existing) {
            $existing->delete();
            $added = false;
        } else {
            PoiTag::create([
                'poi_external_id' => $externalId,
                'user_id' => $userId,
                'tag' => $tag->value,
            ]);
            $added = true;
        }

        $count = PoiTag::query()
            ->where('poi_external_id', $externalId)
            ->where('tag', $tag->value)
            ->count();

        return ['tag' => $tag->value, 'added' => $added, 'count' => $count];
    }
}
