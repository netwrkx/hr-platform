<?php

namespace App\Services;

use App\Events\EmployeeBroadcastEvent;
use App\ServerUI\ColumnConfig;
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    /**
     * Broadcast an employee event to the appropriate WebSocket channel.
     *
     * Broadcasting failures are caught and logged — they must never
     * break handler execution (graceful degradation).
     */
    public function broadcastEmployeeEvent(string $eventType, array $eventData): void
    {
        try {
            $employee = $eventData['data']['employee'];
            $country = $eventData['country'];

            $channel = $this->getChannelName($country);
            $payload = $this->buildPayload($eventType, $employee);

            $this->dispatchBroadcastEvent($channel, $eventType, $payload);

            Log::debug('WebSocket broadcast sent', [
                'channel' => $channel,
                'event_name' => $eventType,
                'payload_size_bytes' => strlen(json_encode($payload)),
            ]);
        } catch (\Throwable $e) {
            Log::error('WebSocket broadcast failed', [
                'event_type' => $eventType,
                'exception_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the broadcast channel name for a country.
     */
    public function getChannelName(string $country): string
    {
        return "employees.{$country}";
    }

    /**
     * Build the broadcast payload with SSN masking.
     */
    public function buildPayload(string $eventType, array $employee): array
    {
        $masked = $employee;
        if (isset($masked['ssn'])) {
            $masked['ssn'] = ColumnConfig::maskSsn($masked['ssn']);
        }

        return [
            'event_type' => $eventType,
            'employee' => $masked,
        ];
    }

    /**
     * Dispatch the broadcast event. Extracted for testability.
     */
    protected function dispatchBroadcastEvent(string $channel, string $eventType, array $payload): void
    {
        event(new EmployeeBroadcastEvent($channel, $eventType, $payload));
    }
}
