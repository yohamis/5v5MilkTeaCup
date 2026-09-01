<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayerStat extends Model
{
    protected $fillable = [
        'match_id', 'player_id', 'team', 'lane', 'kills', 'deaths', 'assists',
        'rating', 'mvp', 'fmvp', 'tea', 'treat',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'mvp' => 'boolean',
            'fmvp' => 'boolean',
            'tea' => 'boolean',
            'treat' => 'boolean',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }
}
