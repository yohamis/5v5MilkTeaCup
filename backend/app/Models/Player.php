<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'pin_hash', 'api_token_hash', 'active'];

    protected $hidden = ['pin_hash', 'api_token_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
