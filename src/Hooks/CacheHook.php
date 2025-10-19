<?php

namespace Doppar\Insight\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Profiler;
use Phaseolies\Application;

/**
 * Hook to intercept cache operations for profiling
 */
class CacheHook implements ProfilerHookInterface
{
    public function register(Application $app, Profiler $profiler): void
    {
        try {
            // Get the current cache store
            $currentCache = $app->make('cache');

            if (! method_exists($currentCache, 'getAdapter')) {
                return;
            }

            // Get the adapter from the current cache
            $adapter = $currentCache->getAdapter();
            $prefix = config('caching.prefix');
            $prefix = is_string($prefix) ? $prefix : null;

            // Replace with profiler cache store
            $profilerCache = new \Doppar\Insight\Cache\ProfilerCacheStore($adapter, $prefix);

            $app->singleton('cache', fn () => $profilerCache);
            $app->singleton(\Psr\SimpleCache\CacheInterface::class, fn () => $profilerCache);
        } catch (\Throwable) {
            // Silently fail if cache is not configured
        }
    }
}
