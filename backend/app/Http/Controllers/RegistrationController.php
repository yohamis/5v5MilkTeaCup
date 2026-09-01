<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterEventRequest;
use App\Models\MatchEvent;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function index(): JsonResponse
    {
        $events = MatchEvent::with(['registrations' => fn ($q) => $q->whereIn('status', ['registered', 'waitlist'])->with('player:id,name')])
            ->where('event_date', '>=', today()->toDateString())->orderBy('event_date')->get();

        return response()->json(['events' => $events]);
    }

    public function store(RegisterEventRequest $request, MatchEvent $event): JsonResponse
    {
        $data = $request->validated();
        $player = $request->attributes->get('player');
        if ($event->status !== 'open') {
            return response()->json(['message' => '报名未开放'], 422);
        }
        if ($event->signup_starts_at && now()->lt($event->signup_starts_at)) {
            return response()->json(['message' => '报名尚未开始'], 422);
        }
        if ($event->signup_ends_at && now()->gt($event->signup_ends_at)) {
            return response()->json(['message' => '报名已经截止'], 422);
        }
        $registration = DB::transaction(function () use ($event, $player, $data): Registration {
            $event = MatchEvent::lockForUpdate()->findOrFail($event->id);
            $registered = Registration::where('match_event_id', $event->id)->where('status', 'registered')->count();
            $waitlisted = Registration::where('match_event_id', $event->id)->where('status', 'waitlist')->count();
            $status = $registered < $event->capacity ? 'registered' : 'waitlist';
            if ($status === 'waitlist' && $waitlisted >= $event->waitlist_capacity) {
                abort(422, '报名和候补均已满');
            }

            return Registration::updateOrCreate(['match_event_id' => $event->id, 'player_id' => $player->id], ['status' => $status, 'note' => $data['note'] ?? null]);
        });

        return response()->json(['registration' => $registration->load('player:id,name')], 201);
    }

    public function destroy(Request $request, MatchEvent $event): JsonResponse
    {
        $player = $request->attributes->get('player');
        DB::transaction(function () use ($event, $player): void {
            MatchEvent::lockForUpdate()->findOrFail($event->id);
            $registration = Registration::where('match_event_id', $event->id)->where('player_id', $player->id)->first();
            $wasRegistered = $registration?->status === 'registered';
            $registration?->update(['status' => 'cancelled']);
            if ($wasRegistered) {
                Registration::where('match_event_id', $event->id)->where('status', 'waitlist')->oldest()->first()?->update(['status' => 'registered']);
            }
        });

        return response()->json(['message' => '已取消报名']);
    }
}
