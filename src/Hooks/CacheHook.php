<?php

namespace Doppar\Insight\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Profiler;
use Phaseolies\Application;
use Phaseolies\Cache\CacheStore;

/**
 * Hook to intercept cache operations for profiling
 */
class CacheHook implements ProfilerHookInterface
{
    public function register(Application $app, Profiler $profiler): void
    {
        try {
            // Get the current cache store
            $currentCache = $app['cache'];

            if (! $currentCache instanceof CacheStore) {
                return;
            }

            // Get the adapter from the current cache
            $adapter = $currentCache->getAdapter();
            // Use the prefix the store really has: the framework makes it safe and never empty.
            $prefix = $currentCache->getPrefix();
            $storeName = config('caching.default');
            $storeName = is_string($storeName) ? $storeName : null;
            $storeDriver = $storeName ? config("caching.stores.{$storeName}.driver") : null;
            $storeDriver = is_string($storeDriver) ? $storeDriver : null;

            // Replace with profiler cache store
            $profilerCache = new \Doppar\Insight\Cache\ProfilerCacheStore($adapter, $prefix, $storeName, $storeDriver);
            $profilerCache->inheritSettingsFrom($currentCache);

            $app->singleton('cache', fn () => $profilerCache);
            $app->singleton(\Psr\SimpleCache\CacheInterface::class, fn () => $profilerCache);
        } catch (\Throwable) {
            // Silently fail if cache is not configured
        }
    }
}
