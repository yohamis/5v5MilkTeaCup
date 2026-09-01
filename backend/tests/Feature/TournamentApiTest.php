<?php

namespace Tests\Feature;

use App\Models\MatchEvent;
use App\Services\TournamentDataService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TournamentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_json_imports_and_exports_without_losing_records(): void
    {
        $payload = json_decode(file_get_contents(base_path('../src/data/tournament.json')), true, flags: JSON_THROW_ON_ERROR);
        $summary = app(TournamentDataService::class)->import($payload);
        $this->assertSame(['matches' => 41, 'players' => 18, 'records' => 410], $summary);
        $response = $this->getJson('/api/tournament')->assertOk();
        $this->assertCount(41, $response->json('matches'));
        $this->assertSame('2026-08-30-a4', $response->json('matches.40.id'));
        $this->assertSame(
            $this->normaliseRatings($payload['matches']),
            $this->normaliseRatings($response->json('matches')),
        );
    }

    public function test_new_player_can_create_account_and_register(): void
    {
        $event = MatchEvent::factory()->create(['event_date' => now()->addDay()->toDateString()]);
        $login = $this->postJson('/api/auth/player', ['name' => '新玩家', 'pin' => '123456', 'new_player' => true])
            ->assertOk()->json();
        $this->withToken($login['token'])->postJson("/api/events/{$event->id}/register")
            ->assertCreated()->assertJsonPath('registration.status', 'registered');
        $this->assertDatabaseHas('registrations', ['match_event_id' => $event->id, 'status' => 'registered']);
        $this->assertDatabaseHas('players', ['name' => '新玩家', 'active' => true]);
    }

    public function test_admin_key_is_required_to_create_event(): void
    {
        config(['tournament.admin_key' => 'test-admin-key']);
        $data = ['event_date' => now()->addDays(2)->toDateString(), 'capacity' => 10, 'status' => 'open'];
        $this->postJson('/api/admin/events', $data)->assertForbidden();
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/api/admin/events', $data)->assertCreated();
    }

    public function test_waitlisted_player_is_promoted_after_cancellation(): void
    {
        $event = MatchEvent::factory()->create(['event_date' => now()->addDays(3)->toDateString(), 'capacity' => 1, 'waitlist_capacity' => 1]);
        $first = $this->postJson('/api/auth/player', ['name' => '一号', 'pin' => '1234', 'new_player' => true])->json('token');
        $second = $this->postJson('/api/auth/player', ['name' => '二号', 'pin' => '1234', 'new_player' => true])->json('token');
        $this->withToken($first)->postJson("/api/events/{$event->id}/register")->assertJsonPath('registration.status', 'registered');
        $this->withToken($second)->postJson("/api/events/{$event->id}/register")->assertJsonPath('registration.status', 'waitlist');
        $this->withToken($first)->deleteJson("/api/events/{$event->id}/register")->assertOk();
        $this->assertDatabaseHas('registrations', ['match_event_id' => $event->id, 'status' => 'registered', 'player_id' => 2]);
    }

    public function test_returns_422_when_admin_upload_is_not_json(): void
    {
        config(['tournament.admin_key' => 'test-admin-key']);

        $this->withHeader('X-Admin-Key', 'test-admin-key')
            ->postJson('/api/admin/tournament/import', ['file' => UploadedFile::fake()->createWithContent('matches.json', '{bad json')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('matches', 0);
    }

    public function test_returns_401_when_player_token_is_missing(): void
    {
        $event = MatchEvent::factory()->create();

        $this->postJson("/api/events/{$event->id}/register")
            ->assertUnauthorized()
            ->assertJsonPath('message', '请先登录');

        $this->assertDatabaseCount('registrations', 0);
    }

    private function normaliseRatings(array $matches): array
    {
        foreach ($matches as &$match) {
            foreach (['blue', 'red'] as $team) {
                foreach ($match['teams'][$team] as &$player) {
                    $player['rating'] = number_format((float) $player['rating'], 1, '.', '');
                }
            }
        }

        return $matches;
    }
}
