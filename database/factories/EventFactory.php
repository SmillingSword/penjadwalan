<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = $this->faker->dateTimeBetween('now', '+1 month');
        $endAt = (clone $startAt)->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return [
            'calendar_id' => Calendar::factory(),
            'title' => $this->faker->sentence(3),
            'description_md' => $this->faker->optional()->paragraph(),
            'location' => $this->faker->optional()->address(),
            'meeting_link' => $this->faker->optional()->url(),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'all_day' => false,
            'timezone' => $this->faker->randomElement(['Asia/Jakarta', 'America/New_York', 'Europe/London', 'Asia/Tokyo']),
            'rrule' => $this->faker->optional(20)->randomElement([
                'FREQ=DAILY;COUNT=5',
                'FREQ=WEEKLY;BYDAY=MO,WE,FR',
                'FREQ=MONTHLY;BYMONTHDAY=15',
                'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=25'
            ]),
            'exdates' => $this->faker->optional(10)->randomElements([
                '2024-01-15',
                '2024-01-22',
                '2024-01-29'
            ], $this->faker->numberBetween(0, 2)),
            'is_private' => $this->faker->boolean(30), // 30% chance of being private
        ];
    }

    /**
     * Indicate that the event is all day.
     */
    public function allDay(): static
    {
        return $this->state(function (array $attributes) {
            $date = $this->faker->dateTimeBetween('now', '+1 month');
            return [
                'start_at' => $date->format('Y-m-d'),
                'end_at' => $date->format('Y-m-d'),
                'all_day' => true,
            ];
        });
    }

    /**
     * Indicate that the event is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the event is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }

    /**
     * Indicate that the event is recurring.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'rrule' => $this->faker->randomElement([
                'FREQ=DAILY;COUNT=10',
                'FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=12',
                'FREQ=MONTHLY;BYMONTHDAY=15;COUNT=6',
                'FREQ=YEARLY;BYMONTH=6;BYMONTHDAY=15;COUNT=5'
            ]),
        ]);
    }

    /**
     * Indicate that the event has a meeting link.
     */
    public function withMeetingLink(): static
    {
        return $this->state(fn (array $attributes) => [
            'meeting_link' => $this->faker->randomElement([
                'https://zoom.us/j/' . $this->faker->numerify('##########'),
                'https://meet.google.com/' . $this->faker->lexify('???-????-???'),
                'https://teams.microsoft.com/l/meetup-join/' . $this->faker->uuid(),
            ]),
        ]);
    }

    /**
     * Indicate that the event has a location.
     */
    public function withLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $this->faker->randomElement([
                'Conference Room A',
                'Meeting Room 1',
                'Office Building, Floor 3',
                $this->faker->address(),
                'Online',
            ]),
        ]);
    }

    /**
     * Create an event for today.
     */
    public function today(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = now()->setTime($this->faker->numberBetween(9, 16), 0, 0);
            $endTime = (clone $startTime)->addHours($this->faker->numberBetween(1, 3));
            
            return [
                'start_at' => $startTime,
                'end_at' => $endTime,
            ];
        });
    }

    /**
     * Create an event for tomorrow.
     */
    public function tomorrow(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = now()->addDay()->setTime($this->faker->numberBetween(9, 16), 0, 0);
            $endTime = (clone $startTime)->addHours($this->faker->numberBetween(1, 3));
            
            return [
                'start_at' => $startTime,
                'end_at' => $endTime,
            ];
        });
    }
}
