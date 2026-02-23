<?php

namespace Tests\Unit;

use App\Handlers\EmployeeCreatedHandler;
use App\Services\BroadcastService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EmployeeCreatedHandlerTest extends TestCase
{
    private CacheService $cacheService;
    private BroadcastService $broadcastService;
    private EmployeeCreatedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
        $this->broadcastService = $this->createMock(BroadcastService::class);
        $this->handler = new EmployeeCreatedHandler($this->cacheService, $this->broadcastService);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    private function usaEventPayload(): array
    {
        return [
            'event_id' => 'uuid-001',
            'event_type' => 'EmployeeCreated',
            'timestamp' => '2026-02-22T00:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 5,
                'changed_fields' => [],
                'employee' => [
                    'id' => 5,
                    'name' => 'John',
                    'last_name' => 'Doe',
                    'salary' => 75000,
                    'country' => 'USA',
                    'ssn' => '123-45-6789',
                    'address' => '123 Main St',
                ],
            ],
        ];
    }

    // ── Cache storage ───────────────────────────────────────────────────

    public function test_stores_employee_in_redis_under_correct_key(): void
    {
        $this->handler->handle($this->usaEventPayload());

        $cached = Cache::get('employee:5');
        $this->assertNotNull($cached);
        $this->assertEquals(5, $cached['id']);
        $this->assertEquals('John', $cached['name']);
        $this->assertEquals('USA', $cached['country']);
    }

    // ── Cache invalidation ──────────────────────────────────────────────

    public function test_invalidates_employee_list_cache_for_country(): void
    {
        // Pre-populate country employee list cache
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['old_list']);

        // Verify cached
        $this->assertEquals(['old_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['new_list']));

        $this->handler->handle($this->usaEventPayload());

        // After handler, cache should be invalidated — callback returns fresh data
        $this->assertEquals(['new_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['new_list']));
    }

    public function test_invalidates_checklist_cache_for_country(): void
    {
        $this->cacheService->rememberChecklist('USA', fn () => ['old_checklist']);
        $this->assertEquals(['old_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));

        $this->handler->handle($this->usaEventPayload());

        $this->assertEquals(['new_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));
    }

    public function test_does_not_invalidate_other_country_caches(): void
    {
        $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['germany_list']);
        $this->cacheService->rememberChecklist('Germany', fn () => ['germany_checklist']);

        $this->handler->handle($this->usaEventPayload());

        // Germany caches should still return cached data (not the fresh callback)
        $this->assertEquals(['germany_list'], $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['fresh']));
        $this->assertEquals(['germany_checklist'], $this->cacheService->rememberChecklist('Germany', fn () => ['fresh']));
    }

    // ── Broadcasting ─────────────────────────────────────────────────────

    public function test_triggers_broadcast_after_caching(): void
    {
        $broadcastService = $this->createMock(BroadcastService::class);
        $broadcastService->expects($this->once())
            ->method('broadcastEmployeeEvent')
            ->with('EmployeeCreated', $this->callback(function ($data) {
                return $data['data']['employee_id'] === 5 && $data['country'] === 'USA';
            }))
            ->willReturnCallback(function () {
                // Verify caching happened before broadcast
                $this->assertNotNull(Cache::get('employee:5'));
            });

        $handler = new EmployeeCreatedHandler($this->cacheService, $broadcastService);
        $handler->handle($this->usaEventPayload());
    }

    // ── Logging ─────────────────────────────────────────────────────────

    public function test_logs_successful_processing(): void
    {
        Log::spy();

        $this->handler->handle($this->usaEventPayload());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return str_contains($message, 'EmployeeCreated processed')
                && $context['employee_id'] === 5
                && $context['country'] === 'USA';
        });
    }
}
