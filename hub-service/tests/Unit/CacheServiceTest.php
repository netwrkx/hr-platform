<?php

namespace Tests\Unit;

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        Redis::flushall();
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    // ── Employee cache (TTL and key structure) ──────────────────────────

    public function test_cache_employee_stores_data_under_correct_key(): void
    {
        $employee = ['id' => 5, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA'];

        $this->cacheService->cacheEmployee(5, $employee);

        $cached = Cache::get('employee:5');
        $this->assertEquals($employee, $cached);
    }

    public function test_cache_employee_has_ttl_not_permanent(): void
    {
        $employee = ['id' => 1, 'name' => 'Jane', 'last_name' => 'Doe', 'salary' => 80000, 'country' => 'USA'];

        $this->cacheService->cacheEmployee(1, $employee);

        // Verify employee is cached and retrievable
        $this->assertNotNull(Cache::get('employee:1'));

        // Verify TTL by scanning raw Redis keys on the cache connection (db 1)
        // Use executeRaw to bypass predis prefix processing
        $client = Redis::connection('cache')->client();
        $allKeys = $client->executeRaw(['KEYS', '*employee:1*']);
        $this->assertNotEmpty($allKeys, 'Employee cache key should exist in Redis');

        $ttl = $client->executeRaw(['TTL', $allKeys[0]]);
        $this->assertGreaterThan(0, $ttl, 'Employee cache key should have a positive TTL');
        $this->assertLessThanOrEqual(300, $ttl, 'Employee cache TTL should not exceed 5 minutes');
    }

    public function test_remove_employee_deletes_cache_key(): void
    {
        $this->cacheService->cacheEmployee(5, ['id' => 5, 'name' => 'John']);

        $this->assertNotNull(Cache::get('employee:5'));

        $this->cacheService->removeEmployee(5);

        $this->assertNull(Cache::get('employee:5'));
    }

    // ── Employee list cache (TTL = 5 minutes) ───────────────────────────

    public function test_remember_employee_list_with_five_minute_ttl(): void
    {
        $result = $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['employee_list']);

        $this->assertEquals(['employee_list'], $result);

        // Verify it's cached by calling again with different callback
        $cached = $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['should_not_compute']);
        $this->assertEquals(['employee_list'], $cached);
    }

    // ── Checklist cache (TTL = 10 minutes) ──────────────────────────────

    public function test_remember_checklist_with_ten_minute_ttl(): void
    {
        $result = $this->cacheService->rememberChecklist('USA', fn () => ['checklist_data']);

        $this->assertEquals(['checklist_data'], $result);

        // Verify it's cached by calling again with different callback
        $cached = $this->cacheService->rememberChecklist('USA', fn () => ['should_not_compute']);
        $this->assertEquals(['checklist_data'], $cached);
    }

    // ── Tag-based invalidation ──────────────────────────────────────────

    public function test_invalidate_country_clears_employee_list_cache(): void
    {
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['page1']);
        $this->cacheService->rememberEmployeeList('USA', 2, 15, fn () => ['page2']);

        // Verify cached
        $this->assertEquals(['page1'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh1']));
        $this->assertEquals(['page2'], $this->cacheService->rememberEmployeeList('USA', 2, 15, fn () => ['fresh2']));

        $this->cacheService->invalidateCountry('USA');

        // After invalidation, callback should be called (cache miss)
        $this->assertEquals(['fresh1'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh1']));
        $this->assertEquals(['fresh2'], $this->cacheService->rememberEmployeeList('USA', 2, 15, fn () => ['fresh2']));
    }

    public function test_invalidate_country_clears_checklist_cache(): void
    {
        $this->cacheService->rememberChecklist('USA', fn () => ['checklist']);

        $this->assertEquals(['checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));

        $this->cacheService->invalidateCountry('USA');

        // After invalidation, callback should produce new data
        $this->assertEquals(['new_checklist'], $this->cacheService->rememberChecklist('USA', fn () => ['new_checklist']));
    }

    public function test_invalidate_country_does_not_affect_other_countries(): void
    {
        $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['usa_data']);
        $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['germany_data']);
        $this->cacheService->rememberChecklist('USA', fn () => ['usa_checklist']);
        $this->cacheService->rememberChecklist('Germany', fn () => ['germany_checklist']);

        $this->cacheService->invalidateCountry('USA');

        // USA caches cleared — callback should produce fresh data
        $this->assertEquals(['fresh_usa'], $this->cacheService->rememberEmployeeList('USA', 1, 15, fn () => ['fresh_usa']));
        $this->assertEquals(['fresh_usa_cl'], $this->cacheService->rememberChecklist('USA', fn () => ['fresh_usa_cl']));

        // Germany caches untouched — original data still returned
        $this->assertEquals(['germany_data'], $this->cacheService->rememberEmployeeList('Germany', 1, 15, fn () => ['fresh_germany']));
        $this->assertEquals(['germany_checklist'], $this->cacheService->rememberChecklist('Germany', fn () => ['fresh_germany_cl']));
    }

    // ── remember (generic) ──────────────────────────────────────────────

    public function test_remember_returns_cached_value_on_hit(): void
    {
        Cache::put('test-key', 'cached-value', 300);

        $callbackCalled = false;
        $result = $this->cacheService->remember('test-key', 300, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return 'fresh-value';
        });

        $this->assertEquals('cached-value', $result);
        $this->assertFalse($callbackCalled);
    }

    public function test_remember_calls_callback_on_miss_and_caches_result(): void
    {
        $result = $this->cacheService->remember('test-key', 300, fn () => 'computed-value');

        $this->assertEquals('computed-value', $result);
        $this->assertEquals('computed-value', Cache::get('test-key'));
    }
}
