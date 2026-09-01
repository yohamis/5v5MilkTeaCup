<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMatchEventRequest;
use App\Http\Requests\UpdateMatchEventRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\TournamentMatch;
use App\Services\TournamentDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

class AdminController extends Controller
{
    public function import(Request $request, TournamentDataService $service): JsonResponse
    {
        $request->validate([
            'file' => ['sometimes', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
            'replace_all' => ['sometimes', 'boolean'],
        ]);

        try {
            $payload = $request->hasFile('file')
                ? json_decode($request->file('file')->get(), true, flags: JSON_THROW_ON_ERROR)
                : $request->except('replace_all');
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => '上传的文件不是有效 JSON']);
        }

        return response()->json([
            'message' => '导入成功',
            'summary' => $service->import($payload, $request->boolean('replace_all', true)),
        ]);
    }

    public function updateMatch(Request $request, string $externalId, TournamentDataService $service): JsonResponse
    {
        $match = $request->validate(['date' => ['required'], 'round' => ['required'], 'winner' => ['required'], 'teams' => ['required', 'array']]);
        $match['id'] = $externalId;

        return response()->json([
            'message' => '比赛已更新',
            'summary' => $service->import(['matches' => [$match]], false),
        ]);
    }

    public function deleteMatch(string $externalId): JsonResponse
    {
        $deleted = TournamentMatch::query()->where('external_id', $externalId)->delete();

        return response()->json(['message' => $deleted ? '比赛已删除' : '比赛不存在'], $deleted ? 200 : 404);
    }

    public function players(): JsonResponse
    {
        return response()->json(['players' => Player::query()->orderBy('name')->get()]);
    }

    public function updatePlayer(UpdatePlayerRequest $request, Player $player): JsonResponse
    {
        $player->update($request->validated());

        return response()->json(['player' => $player]);
    }

    public function events(): JsonResponse
    {
        return response()->json([
            'events' => MatchEvent::with('registrations.player:id,name')->orderByDesc('event_date')->get(),
        ]);
    }

    public function createEvent(StoreMatchEventRequest $request): JsonResponse
    {
        $event = MatchEvent::create($request->validated());

        return response()->json(['event' => $event], 201);
    }

    public function updateEvent(UpdateMatchEventRequest $request, MatchEvent $event): JsonResponse
    {
        $event->update($request->validated());

        return response()->json(['event' => $event]);
    }
}
