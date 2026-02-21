<?php

namespace App\Services;

class CacheService
{
    /**
     * Cache-aside pattern: check cache first, compute on miss, store result.
     *
     * @param string $key    Cache key
     * @param int    $ttl    TTL in seconds
     * @param callable $callback  Computation to run on cache miss
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        // TODO: Implement using Laravel Cache::remember()
        // - Graceful degradation: if Redis is unavailable, call $callback directly
        return $callback();
    }

    /**
     * Invalidate all cache keys matching a country scope.
     *
     * Clears: employees:{country}:*, checklist:{country}
     */
    public function invalidateCountry(string $country): void
    {
        // TODO: Implement tag-based or pattern-based cache invalidation
    }

    /**
     * Cache or update a single employee record.
     */
    public function cacheEmployee(int $id, array $data, int $ttl = 300): void
    {
        // TODO: Store under key employee:{id}
    }

    /**
     * Remove a single employee from cache.
     */
    public function removeEmployee(int $id): void
    {
        // TODO: Delete key employee:{id}
    }
}
