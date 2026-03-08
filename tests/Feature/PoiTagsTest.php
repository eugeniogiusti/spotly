<?php

use App\Models\PoiTag;
use App\Models\User;

const EXTERNAL_ID = 'osm:node:123456';

test('guests cannot fetch tags', function () {
    $this->getJson('/api/pois/'.EXTERNAL_ID.'/tags')
        ->assertUnauthorized();
});

test('guests cannot toggle tags', function () {
    $this->postJson('/api/pois/'.EXTERNAL_ID.'/tags', ['tag' => 'wifi'])
        ->assertUnauthorized();
});

test('authenticated user can fetch tags for a poi', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    PoiTag::create(['poi_external_id' => EXTERNAL_ID, 'user_id' => $other->id, 'tag' => 'wifi']);
    PoiTag::create(['poi_external_id' => EXTERNAL_ID, 'user_id' => $user->id, 'tag' => 'wifi']);
    PoiTag::create(['poi_external_id' => EXTERNAL_ID, 'user_id' => $user->id, 'tag' => 'quiet']);

    $this->actingAs($user)
        ->getJson('/api/pois/'.EXTERNAL_ID.'/tags')
        ->assertOk()
        ->assertJsonPath('counts.wifi', 2)
        ->assertJsonPath('counts.quiet', 1)
        ->assertJsonFragment(['user_tags' => ['wifi', 'quiet']]);
});

test('user can add a tag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/pois/'.EXTERNAL_ID.'/tags', ['tag' => 'laptop_friendly'])
        ->assertOk()
        ->assertJsonPath('tag', 'laptop_friendly')
        ->assertJsonPath('added', true)
        ->assertJsonPath('count', 1);

    expect(PoiTag::where('poi_external_id', EXTERNAL_ID)->where('user_id', $user->id)->where('tag', 'laptop_friendly')->exists())->toBeTrue();
});

test('user can remove a tag by toggling it again', function () {
    $user = User::factory()->create();
    PoiTag::create(['poi_external_id' => EXTERNAL_ID, 'user_id' => $user->id, 'tag' => 'budget_friendly']);

    $this->actingAs($user)
        ->postJson('/api/pois/'.EXTERNAL_ID.'/tags', ['tag' => 'budget_friendly'])
        ->assertOk()
        ->assertJsonPath('added', false)
        ->assertJsonPath('count', 0);

    expect(PoiTag::where('poi_external_id', EXTERNAL_ID)->where('user_id', $user->id)->where('tag', 'budget_friendly')->exists())->toBeFalse();
});

test('invalid tag is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/pois/'.EXTERNAL_ID.'/tags', ['tag' => 'not_a_real_tag'])
        ->assertUnprocessable();
});
