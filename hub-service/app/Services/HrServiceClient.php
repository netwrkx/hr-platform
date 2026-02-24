<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HrServiceClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.hr.url', 'http://hr-service:8000'), '/');
    }

    /**
     * Fetch paginated employees from HR Service.
     * Returns ['data' => [...], 'pagination' => [...]] or null on failure.
     */
    public function fetchEmployees(string $country, int $page, int $perPage): ?array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/employees", [
                'country' => $country,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->successful()) {
                return [
                    'data' => $response->json('data', []),
                    'pagination' => [
                        'total' => $response->json('meta.total', 0),
                        'per_page' => $response->json('meta.per_page', $perPage),
                        'current_page' => $response->json('meta.current_page', $page),
                        'last_page' => $response->json('meta.last_page', 1),
                    ],
                ];
            }

            Log::error('HR Service returned error', [
                'status' => $response->status(),
                'country' => $country,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Failed to fetch employees from HR Service', [
                'country' => $country,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
