<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A POI bookmarked by a user.
 *
 * Stores a denormalized snapshot (name, lat, lng, layer, city) so the user's
 * saved list remains intact even if the POI is evicted from the Overpass cache.
 * Linked back to Poi via poi_external_id → external_id for raw tag access.
 *
 * @property int $user_id
 * @property string $poi_external_id OSM identifier, e.g. "osm:node:123456"
 * @property string $layer
 * @property string $name
 * @property float $lat
 * @property float $lng
 * @property string|null $city Resolved via reverse geocoding at save time
 * @property string|null $notes User's personal notes
 */
class SavedPoi extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'poi_external_id',
        'layer',
        'name',
        'lat',
        'lng',
        'city',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function poi(): HasOne
    {
        return $this->hasOne(Poi::class, 'external_id', 'poi_external_id');
    }
}
