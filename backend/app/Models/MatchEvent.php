<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchEvent extends Model
{
    use HasFactory;

    protected $fillable = ['event_date', 'title', 'signup_starts_at', 'signup_ends_at', 'capacity', 'waitlist_capacity', 'status'];

    protected function casts(): array
    {
        return ['event_date' => 'date:Y-m-d', 'signup_starts_at' => 'datetime', 'signup_ends_at' => 'datetime'];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
