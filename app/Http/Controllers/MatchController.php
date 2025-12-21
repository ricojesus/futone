<?php

namespace App\Http\Controllers;

use App\Services\MatchEngine;
use App\Services\TeamsRepository;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function play(): JsonResponse
    {
        $teams = TeamsRepository::all();

        $engine = new MatchEngine();

        $result = $engine->play($teams[0], $teams[1]);

        return response()->json($result);
    }
}
