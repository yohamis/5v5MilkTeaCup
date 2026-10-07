<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerApiToken extends Model
{
    protected $fillable = ['token_hash'];

    protected $hidden = ['token_hash'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
