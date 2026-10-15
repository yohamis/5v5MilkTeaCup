<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeciderVoteRequest;
use App\Models\MatchEvent;
use App\Models\Registration;
use App\Services\DeciderVoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeciderVoteController extends Controller
{
    public function show(Request $request, MatchEvent $event, DeciderVoteService $deciderVoteService): JsonResponse
    {
        $registration = $this->registeredPlayer($request, $event);
        if (! $registration) {
            return response()->json(['message' => '只有正式席位选手可以参与投票'], 403);
        }

        $event->load('registrations');

        return response()->json([
            'vote' => $registration->decider_vote,
            'summary' => $deciderVoteService->summary($event),
        ]);
    }

    public function update(
        StoreDeciderVoteRequest $request,
        MatchEvent $event,
        DeciderVoteService $deciderVoteService,
    ): JsonResponse {
        if ($event->status !== 'open') {
            return response()->json(['message' => '报名已关闭，投票结果已经锁定'], 422);
        }

        $registration = $this->registeredPlayer($request, $event);
        if (! $registration) {
            return response()->json(['message' => '只有正式席位选手可以参与投票'], 403);
        }

        $registration->update(['decider_vote' => $request->validated('mode')]);
        $event->load('registrations');

        return response()->json([
            'vote' => $registration->decider_vote,
            'summary' => $deciderVoteService->summary($event),
            'message' => '决胜局投票已记录',
        ]);
    }

    private function registeredPlayer(Request $request, MatchEvent $event): ?Registration
    {
        return $event->registrations()
            ->whereBelongsTo($request->attributes->get('player'))
            ->where('status', 'registered')
            ->first();
    }
}
