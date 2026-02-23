<?php

namespace Tests\Unit;

use App\Handlers\EmployeeDeletedHandler;
use App\Services\BroadcastService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EmployeeDeletedHandlerTest extends TestCase
{
    private CacheService $cacheService;
    private BroadcastService $broadcastService;
    private EmployeeDeletedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
        $this->broadcastService = $this->createMock(BroadcastService::class);
        $this->handler = new EmployeeDeletedHandler($this->cacheService, $this->broadcastService);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    private function deleteEventPayload(): array
    {
        return [
            'event_id' => 'uuid-003',
            'event_type' => 'EmployeeDeleted',
            'timestamp' => '2026-02-22T00:00:00+00:00',
            'country' => 'Germany',
            'data' => [
                'employee_id' => 3,
                'changed_fields' => [],
                'employee' => [
                    'id' => 3,
                    'name' => 'Hans',
                    'last_name' => 'Mueller',
                    'salary' => 65000,
                    'country' => 'Germany',
                    'tax_id' => 'DE123456789',
                    'goal' => 'Team lead by Q4',
                ],
            ],
        ];
    }

    // ── Cache removal ───────────────────────────────────────────────────

    public function test_removes_employee_from_redis_cache(): void
    {
        $this->cacheService->cacheEmployee(3, ['id' => 3, 'name' => 'Hans', 'country' => 'Germany']);
        $this->assertNotNull(Cache::get('employee:3'));

        $this->handler->handle($this->deleteEventPayload());

        $this->assertNull(Cache::get('employee:3'));
    }

    public function test_handles_deletion_of_employee_not_in_cache(): void
    {
        // No pre-existing cache entry — should not error
        $this->assertNull(Cache::get('employee:3'));

        $this->handler->handle($this->deleteEventPayload());

        $this->assertNull(Cache::get('employee:3'));
    }

    // ── Cache invalidation ──────────────────────────────────────────────

    public function test_invalidates_employee_list_cache_for_country(): void
    {
        $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['old_list']);
        $this->assertEquals(['old_list'], $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['new_list']));

        $this->handler->handle($this->deleteEventPayload());

        $this->assertEquals(['new_list'], $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['new_list']));
    }

    public function test_invalidates_checklist_cache_for_country(): void
    {
        $this->cacheService->rememberChecklist('Germany', fn () => ['old_checklist']);
        $this->assertEquals(['old_checklist'], $this->cacheService->rememberChecklist('Germany', fn () => ['new_checklist']));

        $this->handler->handle($this->deleteEventPayload());

        $this->assertEquals(['new_checklist'], $this->cacheService->rememberChecklist('Germany', fn () => ['new_checklist']));
    }

    public function test_only_invalidates_correct_country_caches(): void
    {
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['usa_list']);
        $this->cacheService->rememberChecklist('USA', fn () => ['usa_checklist']);

        $this->handler->handle($this->deleteEventPayload());

        // USA caches untouched
        $this->assertEquals(['usa_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh']));
        $this->assertEquals(['usa_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['fresh']));
    }

    // ── Broadcasting ─────────────────────────────────────────────────────

    public function test_triggers_broadcast_after_cache_removal(): void
    {
        // Pre-cache employee so deletion is meaningful
        $this->cacheService->cacheEmployee(3, ['id' => 3, 'name' => 'Hans', 'country' => 'Germany']);

        $broadcastService = $this->createMock(BroadcastService::class);
        $broadcastService->expects($this->once())
            ->method('broadcastEmployeeEvent')
            ->with('EmployeeDeleted', $this->callback(function ($data) {
                return $data['data']['employee_id'] === 3 && $data['country'] === 'Germany';
            }))
            ->willReturnCallback(function () {
                // Verify cache removal happened before broadcast
                $this->assertNull(Cache::get('employee:3'));
            });

        $handler = new EmployeeDeletedHandler($this->cacheService, $broadcastService);
        $handler->handle($this->deleteEventPayload());
    }

    // ── Logging ─────────────────────────────────────────────────────────

    public function test_logs_successful_processing(): void
    {
        Log::spy();

        $this->handler->handle($this->deleteEventPayload());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return str_contains($message, 'EmployeeDeleted processed')
                && $context['employee_id'] === 3
                && $context['country'] === 'Germany';
        });
    }
}
