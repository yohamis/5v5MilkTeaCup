<?php

namespace Database\Factories;

use App\Models\MatchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchEvent> */
class MatchEventFactory extends Factory
{
    protected $model = MatchEvent::class;

    public function definition(): array
    {
        return [
            'event_date' => fake()->unique()->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'title' => '奶茶杯日常赛',
            'capacity' => 10,
            'waitlist_capacity' => 5,
            'status' => 'open',
        ];
    }
}
