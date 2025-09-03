<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }

    /**
     * Create a small organization.
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Small Business Inc.',
                'Local Company',
                'Family Business',
                'Startup Co.',
            ]),
        ]);
    }

    /**
     * Create a large organization.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Global Corporation',
                'International Holdings',
                'Enterprise Solutions Ltd.',
                'Multinational Group',
            ]),
        ]);
    }

    /**
     * Create a tech organization.
     */
    public function tech(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'TechCorp Solutions',
                'Digital Innovations Inc.',
                'Software Systems Ltd.',
                'Cloud Technologies',
                'AI Solutions Group',
            ]),
        ]);
    }
}
