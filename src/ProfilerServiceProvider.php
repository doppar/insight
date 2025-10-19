<?php

namespace Doppar\Insight;

use Phaseolies\Providers\ServiceProvider;

class ProfilerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->mergeConfig(__DIR__ . '/../config/insight.php', 'insight');
        $this->registerProfiler();
    }

    public function boot(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->publishConfiguration();

        /** @var Profiler $profiler */
        $profiler = $this->app->make(Profiler::class);

        if ($profiler->isGloballyEnabled()) {
            $this->registerRoutes();
            $this->registerMiddleware();
            $this->registerHooks($profiler);
        }
    }

    /**
     * Check if Insight is enabled
     */
    protected function isEnabled(): bool
    {
        $config = config('insight');

        return is_array($config) && ! empty($config['enabled']);
    }

    /**
     * Register the Profiler singleton
     */
    protected function registerProfiler(): void
    {
        $this->app->singleton(Profiler::class, function () {
            $config = config('insight');

            // Ensure config is an array
            if (! is_array($config)) {
                $config = [];
            }

            $retentionDays = $config['retention_days'] ?? 1;

            $profiler = new Profiler(
                $config,
                new \Doppar\Insight\Storage\FileStorage(null, $retentionDays)
            );

            $this->registerCollectors($profiler);

            return $profiler;
        });
    }

    /**
     * Publish configuration file
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__ . '/../config/insight.php' => config_path('insight.php'),
        ], 'config');
    }

    /**
     * Register profiler routes
     */
    protected function registerRoutes(): void
    {
        require __DIR__ . '/../routes/profiler.php';
    }

    /**
     * Register profiler middleware
     */
    protected function registerMiddleware(): void
    {
        $router = app('route');

        if (is_object($router) && method_exists($router, 'applyMiddleware')) {
            $router->applyMiddleware(
                app(\Doppar\Insight\Middleware\ProfilerMiddleware::class)
            );
        }
    }

    /**
     * Register profiler hooks
     *
     * Hooks are registered automatically and are not user-configurable.
     * They integrate the profiler with various parts of the application
     * (cache, database, logger, HTTP clients, etc.)
     *
     * @param Profiler $profiler
     * @return void
     */
    protected function registerHooks(Profiler $profiler): void
    {
        $hooks = $this->getHooks();

        foreach ($hooks as $hookClass) {
            try {
                $hook = new $hookClass();
                $hook->register($this->app, $profiler);
            } catch (\Throwable $e) {
                // Silently skip hooks that fail to instantiate or register
            }
        }
    }

    /**
     * Get the list of hooks to register
     *
     * @return array<int, class-string<\Doppar\Insight\Contracts\ProfilerHookInterface>>
     */
    protected function getHooks(): array
    {
        return [
            \Doppar\Insight\Hooks\CacheHook::class,
            \Doppar\Insight\Hooks\AxiosHook::class,
            \Doppar\Insight\Hooks\LoggerHook::class,
            \Doppar\Insight\Hooks\DatabaseHook::class,
        ];
    }

    /**
     * Register profiler collectors
     *
     * Collectors are registered automatically and are not user-configurable.
     * They gather various types of data during request execution
     * (SQL queries, cache operations, logs, HTTP requests, etc.)
     *
     * @param Profiler $profiler
     * @return void
     */
    protected function registerCollectors(Profiler $profiler): void
    {
        $collectors = $this->getCollectors();

        foreach ($collectors as $collectorClass) {
            try {
                $profiler->addCollector(new $collectorClass());
            } catch (\Throwable $e) {
                // Silently skip collectors that fail to instantiate
            }
        }
    }

    /**
     * Get the list of collectors to register
     *
     * @return array<int, class-string<\Doppar\Insight\Contracts\CollectorInterface>>
     */
    protected function getCollectors(): array
    {
        return [
            \Doppar\Insight\Collectors\DopparCollector::class,
            \Doppar\Insight\Collectors\TimeMemoryCollector::class,
            \Doppar\Insight\Collectors\HttpCollector::class,
            \Doppar\Insight\Collectors\SqlCollector::class,
            \Doppar\Insight\Collectors\AuthCollector::class,
            \Doppar\Insight\Collectors\RequestCollector::class,
            \Doppar\Insight\Collectors\ResponseCollector::class,
            \Doppar\Insight\Collectors\SessionCollector::class,
            \Doppar\Insight\Collectors\CacheCollector::class,
            \Doppar\Insight\Collectors\HttpRequestCollector::class,
            \Doppar\Insight\Collectors\LogCollector::class,
        ];
    }
}
