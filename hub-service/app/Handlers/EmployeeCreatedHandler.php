<?php

namespace App\Handlers;

use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class EmployeeCreatedHandler
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    /**
     * Handle an EmployeeCreated event.
     *
     * 1. Store employee data in Redis cache
     * 2. Invalidate country employee list and checklist caches
     * 3. Log successful processing
     */
    public function handle(array $eventData): void
    {
        $employee = $eventData['data']['employee'];
        $employeeId = $eventData['data']['employee_id'];
        $country = $eventData['country'];

        $this->cacheService->cacheEmployee($employeeId, $employee);

        $this->cacheService->invalidateCountry($country);

        Log::info('EmployeeCreated processed', [
            'employee_id' => $employeeId,
            'country' => $country,
        ]);
    }
}
