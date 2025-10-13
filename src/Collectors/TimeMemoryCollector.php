<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class TimeMemoryCollector implements CollectorInterface
{
    protected float $start = 0.0;
    protected int $peak = 0;

    public function name(): string
    {
        return 'timememory';
    }

    public function start(Request $request): void
    {
        $this->start = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);
    }

    public function stop(Request $request, Response $response): void
    {
        $this->peak = memory_get_peak_usage(true);
    }

    public function toArray(): array
    {
        $durationMs = (microtime(true) - $this->start) * 1000.0;
        return [
            'duration_ms' => $durationMs,
            'memory_peak' => $this->peak,
            'time_start' => $this->start,
        ];
    }
}
