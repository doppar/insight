<?php

namespace Doppar\Insight;

use Phaseolies\Providers\ServiceProvider;

class ProfilerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Profiler::class, function () {
            $cfg = config('profiler') ?? [];
            $profiler = new Profiler(is_array($cfg) ? $cfg : [], new \Doppar\Insight\Storage\FileStorage());
            // Register default collectors
            $profiler->addCollector(new \Doppar\Insight\Collectors\TimeMemoryCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\HttpCollector());
            $profiler->addCollector(new \Doppar\Insight\Collectors\SqlCollector());
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
}
