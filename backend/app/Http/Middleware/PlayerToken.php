<?php

namespace App\Http\Middleware;

use App\Models\PlayerApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlayerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $playerApiToken = $token
            ? PlayerApiToken::with('player')->where('token_hash', hash('sha256', $token))->first()
            : null;
        $player = $playerApiToken?->player;

        if (! $player || ! $player->active) {
            return response()->json(['message' => '请先登录'], 401);
        }

        $request->attributes->set('player', $player);
        $request->attributes->set('playerApiToken', $playerApiToken);

        return $next($request);
    }
}
