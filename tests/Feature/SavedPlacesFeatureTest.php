<?php

use App\Models\SavedPoi;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('saving a poi stores the city from reverse geocoding', function () {
    $user = User::factory()->create();

    Http::fake([
        '*nominatim*' => Http::response([
            'address' => ['city' => 'Rome', 'country' => 'Italy'],
        ], 200),
    ]);

    $this->actingAs($user)
        ->postJson('/saved-pois', [
            'poi_external_id' => 'osm:node:1',
            'layer' => 'food',
            'name' => 'Trattoria Roma',
            'lat' => 41.9028,
            'lng' => 12.4964,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('saved_pois', [
        'user_id' => $user->id,
        'poi_external_id' => 'osm:node:1',
        'city' => 'Rome',
    ]);
});

test('deleting a saved poi removes it from the database', function () {
    $user = User::factory()->create();

    SavedPoi::factory()->create([
        'user_id' => $user->id,
        'poi_external_id' => 'osm:node:99',
    ]);

    $this->actingAs($user)
        ->delete('/saved-pois/osm:node:99')
        ->assertNoContent();

    $this->assertDatabaseMissing('saved_pois', [
        'user_id' => $user->id,
        'poi_external_id' => 'osm:node:99',
    ]);
});

test('guest cannot save a poi', function () {
    $this->postJson('/saved-pois', [
        'poi_external_id' => 'osm:node:1',
        'layer' => 'food',
        'name' => 'Test',
        'lat' => 41.9,
        'lng' => 12.4,
    ])->assertUnauthorized();
});
