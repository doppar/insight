<?php

namespace Doppar\Insight\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Profiler;
use Phaseolies\Application;

/**
 * Hook to intercept PDO statements for SQL profiling
 */
class DatabaseHook implements ProfilerHookInterface
{
    public function register(Application $app, Profiler $profiler): void
    {
        try {
            // Install PDO statement class hook to capture SQL timings
            $defaultConnection = (string) config('database.default', 'default');
            $connections = config('insight.database_connections', []);

            if (! is_array($connections) || $connections === []) {
                $connections = [$defaultConnection];
            }

            // Hook all configured database connections
            foreach ($connections as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }

                $name = $name === 'default' ? $defaultConnection : $name;

                try {
                    $pdo = \Phaseolies\Database\Database::getPdoInstance($name);
                    $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
                        \Doppar\Insight\DB\ProfilerPdoStatement::class,
                        [$name, (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)],
                    ]);
                } catch (\Throwable) {
                    // Ignore per-connection errors
                }
            }
        } catch (\Throwable) {
            // Silently fail if DB not configured or not reachable
        }
    }
}
