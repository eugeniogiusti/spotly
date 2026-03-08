<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A POI fetched from the Overpass API and cached in the database.
 *
 * @property string $external_id Unique OSM identifier, e.g. "osm:node:123456"
 * @property string $source Data source, currently always "overpass"
 * @property string $layer Layer key from config/layers.php (e.g. "food")
 * @property string $name Display name of the place
 * @property float $lat
 * @property float $lng
 * @property array $raw_data Full raw element from Overpass (tags, type, etc.)
 * @property \Illuminate\Support\Carbon $cached_at
 */
class Poi extends Model
{
    protected $fillable = [
        'external_id',
        'source',
        'layer',
        'name',
        'lat',
        'lng',
        'raw_data',
        'cached_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'raw_data' => 'array',
            'cached_at' => 'datetime',
        ];
    }
}
