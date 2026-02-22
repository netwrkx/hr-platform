<?php

namespace App\Handlers;

use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class EmployeeDeletedHandler
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    /**
     * Handle an EmployeeDeleted event.
     *
     * 1. Remove employee data from Redis cache
     * 2. Invalidate country employee list and checklist caches
     * 3. Log successful processing
     */
    public function handle(array $eventData): void
    {
        $employeeId = $eventData['data']['employee_id'];
        $country = $eventData['country'];

        $this->cacheService->removeEmployee($employeeId);

        $this->cacheService->invalidateCountry($country);

        Log::info('EmployeeDeleted processed', [
            'employee_id' => $employeeId,
            'country' => $country,
        ]);
    }
}
