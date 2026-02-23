<?php

namespace Tests\Unit;

use App\Events\EmployeeBroadcastEvent;
use App\Services\BroadcastService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BroadcastServiceTest extends TestCase
{
    private BroadcastService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BroadcastService();
    }

    private function eventData(string $country = 'USA', int $employeeId = 1): array
    {
        return [
            'event_id' => 'uuid-test',
            'event_type' => 'EmployeeCreated',
            'timestamp' => '2026-02-23T00:00:00+00:00',
            'country' => $country,
            'data' => [
                'employee_id' => $employeeId,
                'changed_fields' => [],
                'employee' => [
                    'id' => $employeeId,
                    'name' => 'John',
                    'last_name' => 'Doe',
                    'salary' => 75000,
                    'country' => $country,
                    'ssn' => '123-45-6789',
                    'address' => '123 Main St',
                ],
            ],
        ];
    }

    // ── Channel naming ───────────────────────────────────────────────────

    public function test_channel_name_for_usa(): void
    {
        $this->assertEquals('employees.USA', $this->service->getChannelName('USA'));
    }

    public function test_channel_name_for_germany(): void
    {
        $this->assertEquals('employees.Germany', $this->service->getChannelName('Germany'));
    }

    public function test_channel_name_follows_employees_dot_country_format(): void
    {
        $channel = $this->service->getChannelName('USA');
        $this->assertMatchesRegularExpression('/^employees\.\w+$/', $channel);
    }

    // ── Payload structure ────────────────────────────────────────────────

    public function test_payload_includes_event_type(): void
    {
        $employee = ['id' => 1, 'name' => 'John', 'country' => 'USA'];
        $payload = $this->service->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals('EmployeeCreated', $payload['event_type']);
    }

    public function test_payload_includes_employee_data(): void
    {
        $employee = ['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'country' => 'USA'];
        $payload = $this->service->buildPayload('EmployeeCreated', $employee);

        $this->assertArrayHasKey('employee', $payload);
        $this->assertEquals(1, $payload['employee']['id']);
        $this->assertEquals('John', $payload['employee']['name']);
    }

    public function test_payload_masks_ssn(): void
    {
        $employee = ['id' => 1, 'name' => 'John', 'ssn' => '123-45-6789', 'country' => 'USA'];
        $payload = $this->service->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals('***-**-6789', $payload['employee']['ssn']);
    }

    public function test_payload_without_ssn_is_unchanged(): void
    {
        $employee = ['id' => 1, 'name' => 'Hans', 'tax_id' => 'DE123456789', 'country' => 'Germany'];
        $payload = $this->service->buildPayload('EmployeeCreated', $employee);

        $this->assertArrayNotHasKey('ssn', $payload['employee']);
        $this->assertEquals('DE123456789', $payload['employee']['tax_id']);
    }

    // ── Broadcasting dispatches events ───────────────────────────────────

    public function test_broadcasts_created_event_to_correct_channel(): void
    {
        Event::fake([EmployeeBroadcastEvent::class]);

        $this->service->broadcastEmployeeEvent('EmployeeCreated', $this->eventData('USA', 1));

        Event::assertDispatched(EmployeeBroadcastEvent::class, function ($event) {
            $channels = $event->broadcastOn();
            return $channels[0]->name === 'employees.USA'
                && $event->broadcastAs() === 'EmployeeCreated';
        });
    }

    public function test_broadcasts_updated_event_to_correct_channel(): void
    {
        Event::fake([EmployeeBroadcastEvent::class]);

        $data = $this->eventData('Germany', 2);
        $data['event_type'] = 'EmployeeUpdated';

        $this->service->broadcastEmployeeEvent('EmployeeUpdated', $data);

        Event::assertDispatched(EmployeeBroadcastEvent::class, function ($event) {
            $channels = $event->broadcastOn();
            return $channels[0]->name === 'employees.Germany'
                && $event->broadcastAs() === 'EmployeeUpdated';
        });
    }

    public function test_broadcasts_deleted_event_to_correct_channel(): void
    {
        Event::fake([EmployeeBroadcastEvent::class]);

        $data = $this->eventData('USA', 3);
        $data['event_type'] = 'EmployeeDeleted';

        $this->service->broadcastEmployeeEvent('EmployeeDeleted', $data);

        Event::assertDispatched(EmployeeBroadcastEvent::class, function ($event) {
            return $event->broadcastAs() === 'EmployeeDeleted';
        });
    }

    // ── Graceful degradation ─────────────────────────────────────────────

    public function test_broadcast_failure_is_caught_and_logged(): void
    {
        Log::spy();

        // Create a BroadcastService subclass that throws on dispatch
        $service = new class extends BroadcastService {
            protected function dispatchBroadcastEvent(string $channel, string $eventType, array $payload): void
            {
                throw new \RuntimeException('Soketi unavailable');
            }
        };

        // Should not throw
        $service->broadcastEmployeeEvent('EmployeeCreated', $this->eventData());

        Log::shouldHaveReceived('error')->withArgs(function ($message) {
            return str_contains($message, 'WebSocket broadcast failed');
        });
    }

    public function test_broadcast_logs_debug_on_success(): void
    {
        Event::fake([EmployeeBroadcastEvent::class]);
        Log::spy();

        $this->service->broadcastEmployeeEvent('EmployeeCreated', $this->eventData());

        Log::shouldHaveReceived('debug')->withArgs(function ($message) {
            return str_contains($message, 'WebSocket broadcast sent');
        });
    }
}
