<?php

namespace App\Handlers;

use App\Services\BroadcastService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class EmployeeUpdatedHandler
{
    public function __construct(
        private CacheService $cacheService,
        private BroadcastService $broadcastService,
    ) {
    }

    /**
     * Handle an EmployeeUpdated event.
     *
     * 1. Update employee data in Redis cache
     * 2. Invalidate country employee list and checklist caches
     * 3. Broadcast WebSocket event
     * 4. Log successful processing with changed_fields
     */
    public function handle(array $eventData): void
    {
        $employee = $eventData['data']['employee'];
        $employeeId = $eventData['data']['employee_id'];
        $country = $eventData['country'];
        $changedFields = $eventData['data']['changed_fields'] ?? [];

        $this->cacheService->cacheEmployee($employeeId, $employee);
        $this->cacheService->invalidateCountry($country);

        $this->broadcastService->broadcastEmployeeEvent('EmployeeUpdated', $eventData);

        Log::info('EmployeeUpdated processed', [
            'employee_id' => $employeeId,
            'country' => $country,
            'changed_fields' => $changedFields,
        ]);
    }
}
