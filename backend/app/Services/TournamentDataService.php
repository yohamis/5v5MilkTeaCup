<?php

namespace App\Services;

use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TournamentDataService
{
    private const LANES = ['对抗', '打野', '法师', '射手', '辅助'];

    /** @return array{matches: int, players: int, records: int} */
    public function import(array $payload, bool $replaceAll = true): array
    {
        Validator::make($payload, ['matches' => ['required', 'array']])->validate();
        $count = 0;

        DB::transaction(function () use ($payload, $replaceAll, &$count): void {
            if ($replaceAll) {
                TournamentMatch::query()->delete();
            }

            foreach ($payload['matches'] as $index => $item) {
                $this->validateMatch($item, $index);
                $match = TournamentMatch::query()->updateOrCreate(
                    ['external_id' => $item['id']],
                    ['played_on' => $item['date'], 'round' => $item['round'], 'winner' => $item['winner']],
                );
                $match->stats()->delete();

                foreach (['blue', 'red'] as $team) {
                    foreach ($item['teams'][$team] as $playerData) {
                        $player = Player::query()->firstOrCreate(
                            ['name' => trim($playerData['name'])],
                            ['active' => true],
                        );
                        MatchPlayerStat::query()->create([
                            'match_id' => $match->id,
                            'player_id' => $player->id,
                            'team' => $team,
                            'lane' => $playerData['lane'],
                            'kills' => $playerData['kills'],
                            'deaths' => $playerData['deaths'],
                            'assists' => $playerData['assists'],
                            'rating' => $playerData['rating'],
                            'mvp' => $playerData['mvp'],
                            'fmvp' => $playerData['fmvp'],
                            'tea' => $playerData['tea'],
                            'treat' => $playerData['treat'],
                        ]);
                    }
                }
                $count++;
            }
        });

        return [
            'matches' => $count,
            'players' => Player::query()->count(),
            'records' => MatchPlayerStat::query()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        $matches = TournamentMatch::query()
            ->with(['stats' => fn ($query) => $query->with('player:id,name')->orderBy('id')])
            ->orderBy('played_on')
            ->orderBy('round')
            ->get()
            ->map(function (TournamentMatch $match): array {
                $teams = ['blue' => [], 'red' => []];

                foreach ($match->stats as $row) {
                    $teams[$row->team][] = [
                        'name' => $row->player->name,
                        'lane' => $row->lane,
                        'kills' => $row->kills,
                        'deaths' => $row->deaths,
                        'assists' => $row->assists,
                        'rating' => (float) $row->rating,
                        'mvp' => (bool) $row->mvp,
                        'fmvp' => (bool) $row->fmvp,
                        'tea' => (bool) $row->tea,
                        'treat' => (bool) $row->treat,
                    ];
                }

                return [
                    'id' => $match->external_id,
                    'date' => $match->played_on->format('Y-m-d'),
                    'round' => $match->round,
                    'winner' => $match->winner,
                    'teams' => $teams,
                ];
            })
            ->all();

        return [
            'schemaVersion' => 1,
            'competition' => [
                'name' => config('tournament.name'),
                'shortName' => '奶茶杯',
                'season' => config('tournament.season'),
            ],
            'source' => ['file' => 'database', 'generatedAt' => now()->toIso8601String(), 'warnings' => []],
            'matches' => $matches,
        ];
    }

    /** @param array<string, mixed> $item */
    private function validateMatch(array $item, int $index): void
    {
        $validator = Validator::make($item, [
            'id' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d'],
            'round' => ['required', 'string', 'max:20'],
            'winner' => ['required', 'in:blue,red'],
            'teams.blue' => ['required', 'array', 'size:5'],
            'teams.red' => ['required', 'array', 'size:5'],
            'teams.*.*.name' => ['required', 'string', 'max:50'],
            'teams.*.*.lane' => ['required', 'in:'.implode(',', self::LANES)],
            'teams.*.*.kills' => ['required', 'integer', 'min:0'],
            'teams.*.*.deaths' => ['required', 'integer', 'min:0'],
            'teams.*.*.assists' => ['required', 'integer', 'min:0'],
            'teams.*.*.rating' => ['required', 'numeric', 'between:0,99.9'],
            'teams.*.*.mvp' => ['required', 'boolean'],
            'teams.*.*.fmvp' => ['required', 'boolean'],
            'teams.*.*.tea' => ['required', 'boolean'],
            'teams.*.*.treat' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(["matches.$index" => $validator->errors()->all()]);
        }
    }
}
