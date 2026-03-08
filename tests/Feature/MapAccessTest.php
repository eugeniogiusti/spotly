<?php

use App\Models\User;

test('guests are redirected to login when accessing the map', function () {
    $this->get(route('map'))->assertRedirect(route('login'));
});

test('authenticated users can access the map', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('map'))
        ->assertOk();
});
