<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('pin_hash')->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->date('played_on')->index();
            $table->string('round', 20);
            $table->string('winner', 8);
            $table->timestamps();
        });

        Schema::create('match_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->string('team', 8);
            $table->string('lane', 20);
            $table->unsignedSmallInteger('kills')->default(0);
            $table->unsignedSmallInteger('deaths')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->decimal('rating', 4, 1);
            $table->boolean('mvp')->default(false);
            $table->boolean('fmvp')->default(false);
            $table->boolean('tea')->default(false);
            $table->boolean('treat')->default(false);
            $table->timestamps();
            $table->unique(['match_id', 'player_id']);
        });

        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date')->unique();
            $table->string('title')->default('奶茶杯日常赛');
            $table->dateTime('signup_starts_at')->nullable();
            $table->dateTime('signup_ends_at')->nullable();
            $table->unsignedSmallInteger('capacity')->default(10);
            $table->unsignedSmallInteger('waitlist_capacity')->default(5);
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('registered');
            $table->string('note', 200)->nullable();
            $table->timestamps();
            $table->unique(['match_event_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('match_player_stats');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('players');
    }
};
