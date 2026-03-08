<?php

use App\Models\SavedPoi;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('my-places'))->assertRedirect(route('login'));
});

test('authenticated user can view my places page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my-places'))
        ->assertOk();
});

test('saved places appear in the list with correct cities', function () {
    $user = User::factory()->create();

    SavedPoi::factory()->create(['user_id' => $user->id, 'city' => 'Rome', 'layer' => 'food', 'name' => 'Trattoria Romana']);
    SavedPoi::factory()->create(['user_id' => $user->id, 'city' => 'Rome', 'layer' => 'parks', 'name' => 'Villa Borghese']);
    SavedPoi::factory()->create(['user_id' => $user->id, 'city' => 'Barcelona', 'layer' => 'food', 'name' => 'Bar Cañete']);

    $response = $this->actingAs($user)->get(route('my-places'))->assertOk();

    $names = collect($response->inertiaProps('pois.data'))->pluck('name');
    $cities = $response->inertiaProps('cities');

    expect($names)->toContain('Trattoria Romana')
        ->and($names)->toContain('Villa Borghese')
        ->and($names)->toContain('Bar Cañete')
        ->and($cities)->toContain('Rome')
        ->and($cities)->toContain('Barcelona');
});

test('user only sees their own saved places', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    SavedPoi::factory()->create(['user_id' => $user->id, 'city' => 'Rome', 'name' => 'My Place']);
    SavedPoi::factory()->create(['user_id' => $other->id, 'city' => 'Paris', 'name' => 'Other Place']);

    $response = $this->actingAs($user)->get(route('my-places'))->assertOk();

    $names = collect($response->inertiaProps('pois.data'))->pluck('name');

    expect($names)->toContain('My Place')
        ->and($names)->not->toContain('Other Place');
});
