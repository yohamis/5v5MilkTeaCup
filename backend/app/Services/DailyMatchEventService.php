<?php

namespace App\Services;

use App\Models\MatchEvent;

class DailyMatchEventService
{
    public function ensureToday(): MatchEvent
    {
        return MatchEvent::query()->createOrFirst(
            ['event_date' => today()->toDateString()],
            [
                'title' => '奶茶杯日常赛',
                'capacity' => 10,
                'waitlist_capacity' => 5,
                'status' => 'open',
            ],
        );
    }
}
