<?php

namespace App\Http\Controllers;

use App\ServerUI\StepRegistry;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StepsController extends Controller
{
    private const SUPPORTED_COUNTRIES = ['USA', 'Germany'];
    private const STEPS_TTL = 3600; // 60 minutes

    public function __construct(
        private StepRegistry $stepRegistry,
        private CacheService $cacheService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $country = $request->query('country');

        if (!$country) {
            return response()->json(['error' => 'Country parameter is required'], 422);
        }

        if (!in_array($country, self::SUPPORTED_COUNTRIES)) {
            return response()->json(['error' => "Unsupported country: {$country}"], 422);
        }

        $result = $this->cacheService->remember(
            "steps:{$country}",
            self::STEPS_TTL,
            fn () => ['steps' => $this->stepRegistry->getSteps($country)]
        );

        return response()->json($result);
    }
}
