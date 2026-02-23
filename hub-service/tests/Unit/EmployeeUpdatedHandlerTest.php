<?php

namespace Tests\Unit;

use App\Handlers\EmployeeUpdatedHandler;
use App\Services\BroadcastService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EmployeeUpdatedHandlerTest extends TestCase
{
    private CacheService $cacheService;
    private BroadcastService $broadcastService;
    private EmployeeUpdatedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
        $this->broadcastService = $this->createMock(BroadcastService::class);
        $this->handler = new EmployeeUpdatedHandler($this->cacheService, $this->broadcastService);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    private function updateEventPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'uuid-002',
            'event_type' => 'EmployeeUpdated',
            'timestamp' => '2026-02-22T00:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 1,
                'changed_fields' => ['salary'],
                'employee' => [
                    'id' => 1,
                    'name' => 'John',
                    'last_name' => 'Doe',
                    'salary' => 85000,
                    'country' => 'USA',
                    'ssn' => '123-45-6789',
                    'address' => '123 Main St',
                ],
            ],
        ], $overrides);
    }

    // ── Cache update ────────────────────────────────────────────────────

    public function test_updates_employee_in_redis_cache(): void
    {
        // Pre-cache old employee data
        $this->cacheService->cacheEmployee(1, [
            'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
            'salary' => 75000, 'country' => 'USA',
        ]);

        $this->handler->handle($this->updateEventPayload());

        $cached = Cache::get('employee:1');
        $this->assertNotNull($cached);
        $this->assertEquals(85000, $cached['salary']);
    }

    public function test_populates_cache_on_update_even_when_not_cached(): void
    {
        // No pre-existing cache entry
        $this->assertNull(Cache::get('employee:1'));

        $this->handler->handle($this->updateEventPayload());

        $cached = Cache::get('employee:1');
        $this->assertNotNull($cached);
        $this->assertEquals(85000, $cached['salary']);
    }

    // ── Cache invalidation ──────────────────────────────────────────────

    public function test_invalidates_employee_list_cache_for_country(): void
    {
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['old_list']);
        $this->assertEquals(['old_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['new_list']));

        $this->handler->handle($this->updateEventPayload());

        $this->assertEquals(['new_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['new_list']));
    }

    public function test_invalidates_checklist_cache_for_country(): void
    {
        $this->cacheService->rememberChecklist('USA', fn () => ['old_checklist']);
        $this->assertEquals(['old_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));

        $this->handler->handle($this->updateEventPayload());

        $this->assertEquals(['new_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));
    }

    public function test_does_not_invalidate_other_country_caches(): void
    {
        $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['germany_list']);
        $this->cacheService->rememberChecklist('Germany', fn () => ['germany_checklist']);

        $this->handler->handle($this->updateEventPayload());

        $this->assertEquals(['germany_list'], $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['fresh']));
        $this->assertEquals(['germany_checklist'], $this->cacheService->rememberChecklist('Germany', fn () => ['fresh']));
    }

    // ── Edge case: empty changed_fields ─────────────────────────────────

    public function test_handles_empty_changed_fields_without_error(): void
    {
        $payload = $this->updateEventPayload();
        $payload['data']['changed_fields'] = [];

        $this->handler->handle($payload);

        $cached = Cache::get('employee:1');
        $this->assertNotNull($cached);
    }

    // ── Broadcasting ─────────────────────────────────────────────────────

    public function test_triggers_broadcast_after_cache_update(): void
    {
        $broadcastService = $this->createMock(BroadcastService::class);
        $broadcastService->expects($this->once())
            ->method('broadcastEmployeeEvent')
            ->with('EmployeeUpdated', $this->callback(function ($data) {
                return $data['data']['employee_id'] === 1 && $data['country'] === 'USA';
            }))
            ->willReturnCallback(function () {
                // Verify caching happened before broadcast
                $this->assertNotNull(Cache::get('employee:1'));
            });

        $handler = new EmployeeUpdatedHandler($this->cacheService, $broadcastService);
        $handler->handle($this->updateEventPayload());
    }

    // ── Logging ─────────────────────────────────────────────────────────

    public function test_logs_successful_processing_with_changed_fields(): void
    {
        Log::spy();

        $this->handler->handle($this->updateEventPayload());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return str_contains($message, 'EmployeeUpdated processed')
                && $context['employee_id'] === 1
                && $context['country'] === 'USA'
                && $context['changed_fields'] === ['salary'];
        });
    }
}
