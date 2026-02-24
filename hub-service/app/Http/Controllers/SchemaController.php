<?php

namespace App\Http\Controllers;

use App\Http\Resources\SchemaResource;
use App\ServerUI\SchemaBuilder;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemaController extends Controller
{
    private const SUPPORTED_COUNTRIES = ['USA', 'Germany'];
    private const SCHEMA_TTL = 3600; // 60 minutes

    public function __construct(
        private SchemaBuilder $schemaBuilder,
        private CacheService $cacheService,
    ) {
    }

    public function show(Request $request, string $stepId): JsonResponse
    {
        $country = $request->query('country');

        if (!$country) {
            return response()->json(['error' => 'Country parameter is required'], 422);
        }

        if (!in_array($country, self::SUPPORTED_COUNTRIES)) {
            return response()->json(['error' => "Unsupported country: {$country}"], 422);
        }

        try {
            $schema = $this->cacheService->remember(
                "schema:{$stepId}:{$country}",
                self::SCHEMA_TTL,
                fn () => $this->schemaBuilder->getSchema($stepId, $country)
            );

            return (new SchemaResource($schema))->response();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}
