<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Calendar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'owner_user_id' => User::factory(),
            'name' => $this->faker->words(2, true) . ' Calendar',
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
            'is_default' => $this->faker->boolean(20), // 20% chance of being default
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
        ];
    }

    /**
     * Indicate that the calendar is a default calendar.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the calendar is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the calendar is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
