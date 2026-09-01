<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('tournament.admin_key');
        if ($configured === '' || ! hash_equals($configured, (string) $request->header('X-Admin-Key'))) {
            return response()->json(['message' => '管理员密钥无效'], 403);
        }

        return $next($request);
    }
}
