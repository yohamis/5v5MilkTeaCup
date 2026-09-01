<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlayerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $player = $token ? Player::where('api_token_hash', hash('sha256', $token))->where('active', true)->first() : null;
        if (! $player) {
            return response()->json(['message' => '请先登录'], 401);
        }
        $request->attributes->set('player', $player);

        return $next($request);
    }
}
