<?php

namespace Tests\Feature;

use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Registration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeciderVoteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registered_player_can_vote_and_change_decider_mode(): void
    {
        $event = MatchEvent::factory()->create(['capacity' => 1]);
        $player = Player::factory()->create();
        Registration::factory()->for($event, 'event')->for($player)->create();
        $token = $this->tokenFor($player);

        $this->withToken($token)
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'matchmaking'])
            ->assertOk()
            ->assertJsonPath('vote', 'matchmaking')
            ->assertJsonPath('summary.status', 'decided')
            ->assertJsonPath('summary.result', 'matchmaking');

        $this->withToken($token)
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'ranked'])
            ->assertOk()
            ->assertJsonPath('vote', 'ranked')
            ->assertJsonPath('summary.result', 'ranked');

        $this->assertDatabaseHas('registrations', [
            'match_event_id' => $event->id,
            'player_id' => $player->id,
            'decider_vote' => 'ranked',
        ]);
    }

    public function test_returns_422_when_decider_mode_is_invalid(): void
    {
        $event = MatchEvent::factory()->create();
        $player = Player::factory()->create();
        Registration::factory()->for($event, 'event')->for($player)->create();

        $this->withToken($this->tokenFor($player))
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'custom'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');

        $this->assertDatabaseMissing('registrations', ['player_id' => $player->id, 'decider_vote' => 'custom']);
    }

    public function test_returns_403_when_waitlisted_player_votes(): void
    {
        $event = MatchEvent::factory()->create();
        $player = Player::factory()->create();
        Registration::factory()->for($event, 'event')->for($player)->waitlisted()->create();

        $this->withToken($this->tokenFor($player))
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'ranked'])
            ->assertForbidden()
            ->assertJsonPath('message', '只有正式席位选手可以参与投票');

        $this->assertDatabaseHas('registrations', ['player_id' => $player->id, 'decider_vote' => null]);
    }

    public function test_returns_422_when_voting_is_locked(): void
    {
        $event = MatchEvent::factory()->create(['status' => 'closed']);
        $player = Player::factory()->create();
        Registration::factory()->for($event, 'event')->for($player)->create();

        $this->withToken($this->tokenFor($player))
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'ranked'])
            ->assertUnprocessable()
            ->assertJsonPath('message', '报名已关闭，投票结果已经锁定');

        $this->assertDatabaseHas('registrations', ['player_id' => $player->id, 'decider_vote' => null]);
    }

    public function test_ten_complete_votes_use_ranked_mode_as_the_five_to_five_tie_breaker(): void
    {
        $event = MatchEvent::factory()->create(['capacity' => 10]);
        $players = Player::factory()->count(10)->create();

        foreach ($players as $player) {
            Registration::factory()->for($event, 'event')->for($player)->create();
        }

        foreach ($players->take(9) as $index => $player) {
            $mode = $index < 5 ? 'matchmaking' : 'ranked';
            $lastPendingResponse = $this->withToken($this->tokenFor($player))
                ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => $mode]);
        }

        $lastPendingResponse
            ->assertOk()
            ->assertJsonPath('summary.status', 'voting')
            ->assertJsonPath('summary.remaining_votes', 1)
            ->assertJsonPath('summary.result', null);

        $this->withToken($this->tokenFor($players->last()))
            ->putJson("/api/events/{$event->id}/decider-vote", ['mode' => 'ranked'])
            ->assertOk()
            ->assertJsonPath('summary.voted_count', 10)
            ->assertJsonPath('summary.matchmaking_count', 5)
            ->assertJsonPath('summary.ranked_count', 5)
            ->assertJsonPath('summary.status', 'decided')
            ->assertJsonPath('summary.result', 'ranked')
            ->assertJsonPath('summary.tie_break_applied', true);
    }

    public function test_event_list_shows_vote_progress_without_revealing_player_choices(): void
    {
        $event = MatchEvent::factory()->create(['event_date' => today()->toDateString()]);
        $player = Player::factory()->create();
        Registration::factory()->for($event, 'event')->for($player)->create(['decider_vote' => 'ranked']);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('events.0.decider_vote_summary.voted_count', 1)
            ->assertJsonPath('events.0.registrations.0.has_decider_vote', true)
            ->assertJsonMissingPath('events.0.registrations.0.decider_vote');
    }

    private function tokenFor(Player $player): string
    {
        $token = "test-player-token-{$player->id}";
        $player->apiTokens()->firstOrCreate(['token_hash' => hash('sha256', $token)]);

        return $token;
    }
}
