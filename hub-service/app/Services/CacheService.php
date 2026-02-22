<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    private const EMPLOYEE_TTL = 300;      // 5 minutes
    private const EMPLOYEE_LIST_TTL = 300; // 5 minutes
    private const CHECKLIST_TTL = 600;     // 10 minutes

    /**
     * Cache-aside pattern: check cache first, compute on miss, store result.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache or retrieve a paginated employee list for a country.
     * TTL: 5 minutes. Tagged with country::{country} for bulk invalidation.
     */
    public function rememberEmployeeList(string $country, int $page, int $perPage, callable $callback): mixed
    {
        $key = "employees:{$country}:page:{$page}:per_page:{$perPage}";

        return Cache::tags(["country:{$country}"])->remember($key, self::EMPLOYEE_LIST_TTL, $callback);
    }

    /**
     * Cache or retrieve checklist calculation for a country.
     * TTL: 10 minutes. Tagged with country::{country} for bulk invalidation.
     */
    public function rememberChecklist(string $country, callable $callback): mixed
    {
        $key = "checklist:{$country}";

        return Cache::tags(["country:{$country}"])->remember($key, self::CHECKLIST_TTL, $callback);
    }

    /**
     * Invalidate all cache keys for a country scope.
     * Flushes: employees:{country}:*, checklist:{country}
     */
    public function invalidateCountry(string $country): void
    {
        Cache::tags(["country:{$country}"])->flush();
    }

    /**
     * Cache or update a single employee record.
     * TTL: 5 minutes.
     */
    public function cacheEmployee(int $id, array $data, int $ttl = self::EMPLOYEE_TTL): void
    {
        Cache::put("employee:{$id}", $data, $ttl);
    }

    /**
     * Remove a single employee from cache.
     */
    public function removeEmployee(int $id): void
    {
        Cache::forget("employee:{$id}");
    }
}
