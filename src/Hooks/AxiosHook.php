<?php

namespace Doppar\Insight\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Profiler;
use Phaseolies\Application;

/**
 * Hook to intercept Axios HTTP requests for profiling
 */
class AxiosHook implements ProfilerHookInterface
{
    public function register(Application $app, Profiler $profiler): void
    {
        try {
            // Check if Axios is available
            if (! class_exists(\Doppar\Axios\Http\SymfonyHttpClient::class)) {
                return;
            }

            // Register the global hook
            \Doppar\Axios\Http\SymfonyHttpClient::setAfterRequestHook(
                function (string $method, string $url, float $duration, ?int $status, bool $successful) {
                    $collector = \Doppar\Insight\Collectors\HttpRequestCollector::active();
                    if ($collector) {
                        $collector->registerRequest($method, $url, $duration, $status, $successful);
                    }
                }
            );
        } catch (\Throwable) {
            // Silently fail if Axios is not installed or hook fails
        }
    }
}
