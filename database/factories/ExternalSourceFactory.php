<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalSource>
 */
class ExternalSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' ' . $this->faker->numerify('###'),
            'webhook_url' => $this->faker->optional()->url(),
        ];
    }

    /**
     * State: a live-chat widget source (public_key only, no bearer token/webhook).
     *
     * @return static
     */
    public function widget(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => \App\Models\ExternalSource::TYPE_WIDGET,
            'webhook_url' => null,
        ]);
    }
}
