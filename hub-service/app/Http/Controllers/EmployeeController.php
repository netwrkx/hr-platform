<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeCollection;
use App\ServerUI\ColumnConfig;
use App\Services\CacheService;
use App\Services\HrServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private const SUPPORTED_COUNTRIES = ['USA', 'Germany'];
    private const DEFAULT_PER_PAGE = 15;

    public function __construct(
        private CacheService $cacheService,
        private ColumnConfig $columnConfig,
        private HrServiceClient $hrServiceClient,
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

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, (int) $request->query('per_page', self::DEFAULT_PER_PAGE));

        $result = $this->cacheService->rememberEmployeeList($country, $page, $perPage, function () use ($country, $page, $perPage) {
            $allEmployees = $this->cacheService->getEmployeesByCountry($country);

            if (!empty($allEmployees)) {
                $total = count($allEmployees);
                $lastPage = (int) ceil($total / $perPage);
                $offset = ($page - 1) * $perPage;
                $data = array_values(array_slice($allEmployees, $offset, $perPage));

                return [
                    'columns' => $this->columnConfig->getColumns($country),
                    'data' => $data,
                    'pagination' => [
                        'total' => $total,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => $lastPage,
                    ],
                ];
            }

            // Cold start / Redis flush — fallback to HR Service HTTP
            return $this->fetchFromHrService($country, $page, $perPage);
        });

        return (new EmployeeCollection($result))->response();
    }

    private function fetchFromHrService(string $country, int $page, int $perPage): array
    {
        $result = $this->hrServiceClient->fetchEmployees($country, $page, $perPage);

        if ($result === null) {
            return [
                'columns' => $this->columnConfig->getColumns($country),
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => 1,
                ],
            ];
        }

        return [
            'columns' => $this->columnConfig->getColumns($country),
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ];
    }
}
