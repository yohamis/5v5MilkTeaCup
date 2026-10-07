<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('players')
            ->whereNotNull('api_token_hash')
            ->orderBy('id')
            ->get(['id', 'api_token_hash'])
            ->each(function (object $player) use ($now): void {
                DB::table('player_api_tokens')->insertOrIgnore([
                    'player_id' => $player->id,
                    'token_hash' => $player->api_token_hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_api_tokens');
    }
};
