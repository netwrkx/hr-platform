<?php

namespace Tests\Feature;

use App\Services\BroadcastService;
use App\Services\CacheService;
use App\Services\EventConsumer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BroadcastFeatureTest extends TestCase
{
    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    private function employeeEventPayload(string $eventType, string $country = 'USA', int $id = 1): string
    {
        return json_encode([
            'event_id' => 'feat-uuid-' . $id,
            'event_type' => $eventType,
            'timestamp' => '2026-02-23T00:00:00+00:00',
            'country' => $country,
            'data' => [
                'employee_id' => $id,
                'changed_fields' => $eventType === 'EmployeeUpdated' ? ['salary'] : [],
                'employee' => [
                    'id' => $id,
                    'name' => 'Alice',
                    'last_name' => 'Smith',
                    'salary' => 90000,
                    'country' => $country,
                ],
            ],
        ]);
    }

    // ── Handler execution with broadcasting ──────────────────────────────

    public function test_handler_completes_without_throwing_when_broadcasting(): void
    {
        $consumer = $this->app->make(EventConsumer::class);

        $result = $consumer->processMessage($this->employeeEventPayload('EmployeeCreated'));

        $this->assertEquals(EventConsumer::ACK, $result);
        $this->assertNotNull(Cache::get('employee:1'));
    }

    public function test_updated_handler_completes_with_broadcasting(): void
    {
        $this->cacheService->cacheEmployee(1, [
            'id' => 1, 'name' => 'Alice', 'salary' => 80000, 'country' => 'USA',
        ]);

        $consumer = $this->app->make(EventConsumer::class);

        $result = $consumer->processMessage($this->employeeEventPayload('EmployeeUpdated'));

        $this->assertEquals(EventConsumer::ACK, $result);
        $this->assertEquals(90000, Cache::get('employee:1')['salary']);
    }

    public function test_deleted_handler_completes_with_broadcasting(): void
    {
        $this->cacheService->cacheEmployee(1, [
            'id' => 1, 'name' => 'Alice', 'salary' => 90000, 'country' => 'USA',
        ]);

        $consumer = $this->app->make(EventConsumer::class);

        $result = $consumer->processMessage($this->employeeEventPayload('EmployeeDeleted'));

        $this->assertEquals(EventConsumer::ACK, $result);
        $this->assertNull(Cache::get('employee:1'));
    }

    // ── Graceful degradation ─────────────────────────────────────────────

    public function test_broadcast_failure_does_not_break_handler_execution(): void
    {
        Log::spy();

        // Bind a BroadcastService that always throws
        $this->app->bind(BroadcastService::class, function () {
            return new class extends BroadcastService {
                protected function dispatchBroadcastEvent(string $channel, string $eventType, array $payload): void
                {
                    throw new \RuntimeException('Soketi connection refused');
                }
            };
        });

        $consumer = $this->app->make(EventConsumer::class);

        $result = $consumer->processMessage($this->employeeEventPayload('EmployeeCreated'));

        // Handler should still complete — cache updated, ACK returned
        $this->assertEquals(EventConsumer::ACK, $result);
        $this->assertNotNull(Cache::get('employee:1'));

        // Error should be logged
        Log::shouldHaveReceived('error')->withArgs(function ($message) {
            return str_contains($message, 'WebSocket broadcast failed');
        });
    }
}
