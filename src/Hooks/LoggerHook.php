<?php

namespace Doppar\Insight\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Profiler;
use Phaseolies\Application;

/**
 * Hook to intercept logger calls for profiling
 */
class LoggerHook implements ProfilerHookInterface
{
    public function register(Application $app, Profiler $profiler): void
    {
        try {
            // Create a persistent profiler handler
            $profilerHandler = new \Doppar\Insight\Handlers\ProfilerLogHandler();

            // Create a persistent handler wrapper
            $persistentHandler = new class ($profilerHandler) implements \Phaseolies\Logger\Contracts\LogHandlerInterface {
                private \Doppar\Insight\Handlers\ProfilerLogHandler $handler;

                public function __construct(\Doppar\Insight\Handlers\ProfilerLogHandler $handler)
                {
                    $this->handler = $handler;
                }

                public function configureHandler(\Monolog\Logger $logger, string $channel): void
                {
                    $logger->pushHandler($this->handler);
                }
            };

            // Wrap the logger service to persist our handler across reset() calls
            $app->extend('log', function ($logger) use ($persistentHandler) {
                if (! $logger instanceof \Phaseolies\Support\LoggerService) {
                    return $logger;
                }

                // Return our wrapper that re-adds the handler before each log call
                return new \Doppar\Insight\Support\ProfilerLoggerWrapper($logger, $persistentHandler);
            });
        } catch (\Throwable) {
            // Silently fail if logger is not configured
        }
    }
}
