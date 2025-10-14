<?php

namespace Doppar\Insight;

use Phaseolies\Providers\ServiceProvider;

class ProfilerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Profiler::class, function () {
            $cfg = config('profiler') ?? [];
            $retentionDays = is_array($cfg) ? ($cfg['retention_days'] ?? 1) : 1;
            $profiler = new Profiler(is_array($cfg) ? $cfg : [], new \Doppar\Insight\Storage\FileStorage(null, $retentionDays));
            // Register default collectors
            $profiler->addCollector(new \Doppar\Insight\Collectors\DopparCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\TimeMemoryCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\HttpCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\SqlCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\AuthCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\RequestCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\ResponseCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\SessionCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\CacheCollector());
            
            return $profiler;
        });
    }

    public function boot(): void
    {
        /** @var Profiler $profiler */
        $profiler = $this->app->make(Profiler::class);

        // Register routes only if enabled (protects prod)
        if ($profiler->isGloballyEnabled()) {
            // Load package routes
            require __DIR__ . '/../routes/profiler.php';

            $router = app('route');
            if (method_exists($router, 'applyMiddleware')) {
                $router->applyMiddleware(app(\Doppar\Insight\Middleware\ProfilerMiddleware::class));
            }

            // Replace cache store with profiler cache store to track operations
            $this->replaceCache();

            // Install PDO statement class hook to capture SQL timings without touching the framework
            try {
                $defaultPdo = \Phaseolies\Database\Database::getPdoInstance();
                if ($defaultPdo) {
                    $defaultPdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\Doppar\Insight\DB\ProfilerPdoStatement::class, []]);
                }

                $connections = config('database.connections') ?? [];
                if (is_array($connections)) {
                    foreach (array_keys($connections) as $name) {
                        try {
                            $pdo = \Phaseolies\Database\Database::getPdoInstance($name);
                            $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\Doppar\Insight\DB\ProfilerPdoStatement::class, []]);
                        } catch (\Throwable) { /* ignore per-connection errors */ }
                    }
                }
            } catch (\Throwable) {
                // ignore if DB not configured or not reachable
            }
        }
    }

    protected function replaceCache(): void
    {
        try {
            // Get the current cache store
            $currentCache = $this->app->make('cache');
            if (!$currentCache instanceof \Phaseolies\Cache\CacheStore) {
                return;
            }

            // Get the adapter from the current cache
            $adapter = $currentCache->getAdapter();
            $prefix = config('caching.prefix');

            // Replace with profiler cache store
            $profilerCache = new \Doppar\Insight\Cache\ProfilerCacheStore($adapter, $prefix);
            
            $this->app->singleton('cache', fn() => $profilerCache);
            $this->app->singleton(\Psr\SimpleCache\CacheInterface::class, fn() => $profilerCache);
        } catch (\Throwable) {
            // Silently fail if cache is not configured
        }
    }
}
