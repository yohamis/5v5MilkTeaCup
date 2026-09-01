<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = ['external_id', 'played_on', 'round', 'winner'];

    protected function casts(): array
    {
        return ['played_on' => 'date:Y-m-d'];
    }

    public function stats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class, 'match_id');
    }
}
