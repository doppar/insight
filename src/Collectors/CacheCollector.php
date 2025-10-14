<?php

namespace Doppar\Insight\Collectors;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Support\Facades\Cache;
use Doppar\Insight\Contracts\CollectorInterface;

class CacheCollector implements CollectorInterface
{
    protected array $operations = [];
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
        return 'cache';
    }

    public function start(Request $request): void
    {
        $this->operations = [];
        self::setActive($this);
    }

    public function stop(Request $request, Response $response): void
    {
        self::setActive(null);
    }

    /**
     * Register a cache operation
     */
    public function registerOperation(string $type, string $key, mixed $value = null, bool $hit = false): void
    {
        $this->operations[] = [
            'type' => $type,
            'key' => $key,
            'value' => $this->formatValue($value),
            'hit' => $hit,
            'time' => microtime(true),
        ];
    }

    protected function formatValue(mixed $value): mixed
    {
        // Limit value size for display
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        if (is_array($value) || is_object($value)) {
            return '[' . gettype($value) . ']';
        }
        return $value;
    }

    public function toArray(): array
    {
        $hits = 0;
        $misses = 0;
        $writes = 0;
        $deletes = 0;

        foreach ($this->operations as $op) {
            match ($op['type']) {
                'get' => $op['hit'] ? $hits++ : $misses++,
                'set', 'forever' => $writes++,
                'delete', 'forget' => $deletes++,
                default => null,
            };
        }

        return [
            'cache_operations' => $this->operations,
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'cache_writes' => $writes,
            'cache_deletes' => $deletes,
            'cache_total' => count($this->operations),
        ];
    }
}
