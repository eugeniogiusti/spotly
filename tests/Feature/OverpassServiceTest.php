<?php

use App\Services\OverpassService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new OverpassService;
});

it('normalizes a node element into a POI', function () {
    Http::fake([
        '*' => Http::response([
            'elements' => [
                [
                    'type' => 'node',
                    'id' => 123456,
                    'lat' => 41.9028,
                    'lon' => 12.4964,
                    'tags' => ['name' => 'Trattoria Roma', 'amenity' => 'restaurant', 'addr:street' => 'Via Roma'],
                ],
            ],
        ], 200),
    ]);

    $pois = $this->service->fetch('41.8,12.4,42.0,12.6', 'food');

    expect($pois)->toHaveCount(1)
        ->and($pois[0]['external_id'])->toBe('osm:node:123456')
        ->and($pois[0]['source'])->toBe('overpass')
        ->and($pois[0]['layer'])->toBe('food')
        ->and($pois[0]['name'])->toBe('Trattoria Roma')
        ->and($pois[0]['lat'])->toBe(41.9028)
        ->and($pois[0]['lng'])->toBe(12.4964);
});

it('normalizes a way element using the center coordinates', function () {
    Http::fake([
        '*' => Http::response([
            'elements' => [
                [
                    'type' => 'way',
                    'id' => 789,
                    'center' => ['lat' => 48.8566, 'lon' => 2.3522],
                    'tags' => ['name' => 'Parc des Buttes-Chaumont', 'leisure' => 'park', 'addr:street' => 'Rue Botzaris'],
                ],
            ],
        ], 200),
    ]);

    $pois = $this->service->fetch('48.8,2.3,48.9,2.4', 'parks');

    expect($pois[0]['external_id'])->toBe('osm:way:789')
        ->and($pois[0]['lat'])->toBe(48.8566)
        ->and($pois[0]['lng'])->toBe(2.3522);
});

it('returns an empty array when Overpass returns no elements', function () {
    Http::fake([
        '*' => Http::response(['elements' => []], 200),
    ]);

    $pois = $this->service->fetch('41.8,12.4,42.0,12.6', 'food');

    expect($pois)->toBeEmpty();
});

it('throws a RuntimeException for an unknown layer', function () {
    $this->service->fetch('41.8,12.4,42.0,12.6', 'nonexistent');
})->throws(RuntimeException::class, 'Unknown layer: nonexistent');

it('throws a RuntimeException when Overpass API returns an error', function () {
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    $this->service->fetch('41.8,12.4,42.0,12.6', 'food');
})->throws(RuntimeException::class);
