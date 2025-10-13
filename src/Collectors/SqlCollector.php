<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class SqlCollector implements CollectorInterface
{
    /** @var array<int, array<string, mixed>> */
    protected array $queries = [];
    protected float $totalTimeMs = 0.0;

    protected static ?self $active = null;

    public static function setActive(?self $collector): void
    {
        self::$active = $collector;
    }

    public static function active(): ?self
    {
        return self::$active;
    }

    public function name(): string
    {
        return 'sql';
    }

    public function start(Request $request): void
    {
        $this->queries = [];
        $this->totalTimeMs = 0.0;
        self::setActive($this);
    }

    public function stop(Request $request, Response $response): void
    {
        // nothing else for now
        self::setActive(null);
    }

    public function registerQuery(string $sql, array $bindings, float $durationMs, ?int $rowCount = null, ?string $error = null): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'error' => $error,
        ];
        $this->totalTimeMs += $durationMs;
    }

    public function toArray(): array
    {
        // Limit the list size in payload to avoid huge responses
        $maxList = 50;
        $list = count($this->queries) > $maxList ? array_slice($this->queries, 0, $maxList) : $this->queries;

        return [
            'sql_total_count' => count($this->queries),
            'sql_total_time_ms' => $this->totalTimeMs,
            'sql' => $list,
        ];
    }
}
