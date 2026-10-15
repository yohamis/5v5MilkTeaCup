<?php

namespace App\Services;

use App\Models\MatchEvent;

class DeciderVoteService
{
    public const MATCHMAKING = 'matchmaking';

    public const RANKED = 'ranked';

    /**
     * @return array{
     *     registered_count: int,
     *     required_votes: int,
     *     voted_count: int,
     *     remaining_votes: int,
     *     matchmaking_count: int,
     *     ranked_count: int,
     *     status: string,
     *     result: string|null,
     *     tie_break_applied: bool
     * }
     */
    public function summary(MatchEvent $event): array
    {
        $registrations = $event->relationLoaded('registrations')
            ? $event->registrations
            : $event->registrations()->get();
        $registered = $registrations->where('status', 'registered');
        $registeredCount = $registered->count();
        $requiredVotes = max((int) $event->capacity, $registeredCount);
        $matchmakingCount = $registered->where('decider_vote', self::MATCHMAKING)->count();
        $rankedCount = $registered->where('decider_vote', self::RANKED)->count();
        $votedCount = $matchmakingCount + $rankedCount;
        $rosterReady = $registeredCount >= (int) $event->capacity;
        $votingComplete = $rosterReady && $votedCount === $registeredCount;
        $tieBreakApplied = $votingComplete && $matchmakingCount === $rankedCount;
        $result = null;

        if ($votingComplete) {
            $result = $matchmakingCount > $rankedCount ? self::MATCHMAKING : self::RANKED;
        }

        return [
            'registered_count' => $registeredCount,
            'required_votes' => $requiredVotes,
            'voted_count' => $votedCount,
            'remaining_votes' => max(0, $requiredVotes - $votedCount),
            'matchmaking_count' => $matchmakingCount,
            'ranked_count' => $rankedCount,
            'status' => ! $rosterReady ? 'waiting_roster' : ($votingComplete ? 'decided' : 'voting'),
            'result' => $result,
            'tie_break_applied' => $tieBreakApplied,
        ];
    }
}
