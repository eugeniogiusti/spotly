<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A community tag cast by a user on a POI.
 *
 * Tags are toggled (one row per user/poi/tag combination).
 * No updated_at since tags are never modified, only created or deleted.
 *
 * @property string $poi_external_id
 * @property int $user_id
 * @property string $tag Value from PoiTagEnum
 */
class PoiTag extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['poi_external_id', 'user_id', 'tag'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
