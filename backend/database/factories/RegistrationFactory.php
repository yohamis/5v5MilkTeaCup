<?php

namespace Database\Factories;

use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'match_event_id' => MatchEvent::factory(),
            'player_id' => Player::factory(),
            'status' => 'registered',
            'note' => null,
            'decider_vote' => null,
        ];
    }

    public function waitlisted(): static
    {
        return $this->state(fn (): array => ['status' => 'waitlist']);
    }
}
