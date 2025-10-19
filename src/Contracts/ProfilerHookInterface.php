<?php

namespace Doppar\Insight\Contracts;

use Doppar\Insight\Profiler;
use Phaseolies\Application;

/**
 * Interface for profiler hooks
 *
 * Hooks are used to integrate the profiler with various parts of the application
 * (cache, database, logger, HTTP clients, etc.) without modifying the core profiler code.
 */
interface ProfilerHookInterface
{
    /**
     * Register the hook with the application
     *
     * @param Application $app The application instance
     * @param Profiler $profiler The profiler instance
     * @return void
     */
    public function register(Application $app, Profiler $profiler): void;
}
