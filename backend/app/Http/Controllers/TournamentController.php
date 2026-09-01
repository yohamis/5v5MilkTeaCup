<?php

namespace App\Http\Controllers;

use App\Services\TournamentDataService;
use Illuminate\Http\JsonResponse;

class TournamentController extends Controller
{
    public function __invoke(TournamentDataService $service): JsonResponse
    {
        return response()->json($service->export());
    }
}
