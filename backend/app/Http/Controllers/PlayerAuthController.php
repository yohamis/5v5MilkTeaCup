<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerLoginRequest;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlayerAuthController extends Controller
{
    public function login(PlayerLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $player = Player::where('name', trim($data['name']))->first();
        if (! $player) {
            if (! ($data['new_player'] ?? false)) {
                return response()->json(['message' => '玩家不存在，请选择创建新玩家'], 404);
            }
            $player = Player::create(['name' => trim($data['name']), 'pin_hash' => Hash::make($data['pin'])]);
        } elseif ($data['new_player'] ?? false) {
            return response()->json(['message' => '该昵称已经存在'], 409);
        } elseif ($player->pin_hash && ! Hash::check($data['pin'], $player->pin_hash)) {
            return response()->json(['message' => 'PIN 不正确'], 422);
        } elseif (! $player->pin_hash) {
            $player->pin_hash = Hash::make($data['pin']);
        }
        $token = Str::random(48);
        $player->api_token_hash = hash('sha256', $token);
        $player->save();

        return response()->json(['token' => $token, 'player' => $player]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['player' => $request->attributes->get('player')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        $player->update(['api_token_hash' => null]);

        return response()->json(['message' => '已退出']);
    }
}
