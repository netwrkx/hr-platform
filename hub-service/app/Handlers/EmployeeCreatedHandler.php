<?php

namespace App\Handlers;

use App\Services\BroadcastService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class EmployeeCreatedHandler
{
    public function __construct(
        private CacheService $cacheService,
        private BroadcastService $broadcastService,
    ) {
    }

    /**
     * Handle an EmployeeCreated event.
     *
     * 1. Store employee data in Redis cache
     * 2. Invalidate country employee list and checklist caches
     * 3. Broadcast WebSocket event
     * 4. Log successful processing
     */
    public function handle(array $eventData): void
    {
        $employee = $eventData['data']['employee'];
        $employeeId = $eventData['data']['employee_id'];
        $country = $eventData['country'];

        $this->cacheService->cacheEmployee($employeeId, $employee);
        $this->cacheService->invalidateCountry($country);

        $this->broadcastService->broadcastEmployeeEvent('EmployeeCreated', $eventData);

        Log::info('EmployeeCreated processed', [
            'employee_id' => $employeeId,
            'country' => $country,
        ]);
    }
}
