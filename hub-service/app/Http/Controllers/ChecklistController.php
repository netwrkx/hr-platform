<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChecklistCollection;
use App\Services\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    private const SUPPORTED_COUNTRIES = ['USA', 'Germany'];

    public function __construct(private ChecklistService $checklistService)
    {
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

        $result = $this->checklistService->evaluate($country);

        return (new ChecklistCollection($result))->response();
    }
}
