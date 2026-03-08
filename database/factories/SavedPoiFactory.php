<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavedPoi>
 */
class SavedPoiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'poi_external_id' => 'osm:node:'.$this->faker->unique()->numberBetween(1000, 999999),
            'layer' => $this->faker->randomElement(['food', 'parks', 'markets', 'work', 'transport']),
            'name' => $this->faker->company(),
            'lat' => $this->faker->latitude(41.0, 42.0),
            'lng' => $this->faker->longitude(12.0, 13.0),
            'city' => $this->faker->city(),
        ];
    }
}
