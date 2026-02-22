<?php

namespace Tests\Feature;

use App\Handlers\EmployeeCreatedHandler;
use App\Handlers\EmployeeDeletedHandler;
use App\Handlers\EmployeeUpdatedHandler;
use App\Services\CacheService;
use App\Services\EventConsumer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EventHandlerIntegrationTest extends TestCase
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

    // ── Full flow: event → consumer → handler → cache ───────────────────

    public function test_created_event_through_consumer_caches_employee(): void
    {
        $consumer = $this->app->make(EventConsumer::class);

        $payload = json_encode([
            'event_id' => 'int-uuid-1',
            'event_type' => 'EmployeeCreated',
            'timestamp' => '2026-02-22T00:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 10,
                'changed_fields' => [],
                'employee' => [
                    'id' => 10,
                    'name' => 'Alice',
                    'last_name' => 'Smith',
                    'salary' => 90000,
                    'country' => 'USA',
                ],
            ],
        ]);

        $result = $consumer->processMessage($payload);

        $this->assertEquals(EventConsumer::ACK, $result);

        // Employee should now be cached
        $cached = Cache::get('employee:10');
        $this->assertNotNull($cached);
        $this->assertEquals('Alice', $cached['name']);
        $this->assertEquals(90000, $cached['salary']);
    }

    public function test_updated_event_through_consumer_updates_cache(): void
    {
        // Pre-cache employee
        $this->cacheService->cacheEmployee(10, [
            'id' => 10, 'name' => 'Alice', 'last_name' => 'Smith',
            'salary' => 90000, 'country' => 'USA',
        ]);

        $consumer = $this->app->make(EventConsumer::class);

        $payload = json_encode([
            'event_id' => 'int-uuid-2',
            'event_type' => 'EmployeeUpdated',
            'timestamp' => '2026-02-22T01:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 10,
                'changed_fields' => ['salary'],
                'employee' => [
                    'id' => 10,
                    'name' => 'Alice',
                    'last_name' => 'Smith',
                    'salary' => 95000,
                    'country' => 'USA',
                ],
            ],
        ]);

        $result = $consumer->processMessage($payload);

        $this->assertEquals(EventConsumer::ACK, $result);

        $cached = Cache::get('employee:10');
        $this->assertNotNull($cached);
        $this->assertEquals(95000, $cached['salary']);
    }

    public function test_deleted_event_through_consumer_removes_cache(): void
    {
        // Pre-cache employee
        $this->cacheService->cacheEmployee(10, [
            'id' => 10, 'name' => 'Alice', 'last_name' => 'Smith',
            'salary' => 95000, 'country' => 'USA',
        ]);
        $this->assertNotNull(Cache::get('employee:10'));

        $consumer = $this->app->make(EventConsumer::class);

        $payload = json_encode([
            'event_id' => 'int-uuid-3',
            'event_type' => 'EmployeeDeleted',
            'timestamp' => '2026-02-22T02:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 10,
                'changed_fields' => [],
                'employee' => [
                    'id' => 10,
                    'name' => 'Alice',
                    'last_name' => 'Smith',
                    'salary' => 95000,
                    'country' => 'USA',
                ],
            ],
        ]);

        $result = $consumer->processMessage($payload);

        $this->assertEquals(EventConsumer::ACK, $result);
        $this->assertNull(Cache::get('employee:10'));
    }

    // ── Cache immediately invalidated on event arrival ──────────────────

    public function test_cache_immediately_invalidated_when_event_arrives(): void
    {
        // Pre-populate country caches
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['stale_list']);
        $this->cacheService->rememberChecklist('USA', fn () => ['stale_checklist']);

        // Verify cached
        $this->assertEquals(['stale_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh_list']));
        $this->assertEquals(['stale_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['fresh_checklist']));

        $consumer = $this->app->make(EventConsumer::class);

        $payload = json_encode([
            'event_id' => 'int-uuid-4',
            'event_type' => 'EmployeeCreated',
            'timestamp' => '2026-02-22T03:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 20,
                'changed_fields' => [],
                'employee' => [
                    'id' => 20,
                    'name' => 'Bob',
                    'last_name' => 'Jones',
                    'salary' => 70000,
                    'country' => 'USA',
                ],
            ],
        ]);

        $consumer->processMessage($payload);

        // Both caches should be invalidated — fresh data returned
        $this->assertEquals(['fresh_list'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh_list']));
        $this->assertEquals(['fresh_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['fresh_checklist']));
    }
}
