<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Support\SensitiveDataSanitizer;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class CacheCollector implements CollectorInterface
{
    use UsesSensitiveDataSanitizer;

    /** @var array<int, array<string, mixed>> */
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
     * Register a cache operation.
     *
     * @param array<string, mixed> $meta
     */
    public function registerOperation(
        string $type,
        string $key,
        mixed $value = null,
        bool $hit = false,
        array $meta = []
    ): void {
        $sanitizer = $this->sanitizer();
        $value = $sanitizer->sanitize($value);
        $normalized = $sanitizer->sanitize($this->normalizedForJson($value));
        $valueJson = null;
        if ($normalized !== null) {
            $encoded = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $valueJson = $encoded === false ? null : (strlen($encoded) > 10000 ? substr($encoded, 0, 10000) . "\n… (truncated)" : $encoded);
        }

        $operation = [
            'type' => $type,
            'key' => $key,
            'value' => $this->sanitizeFormattedValue($this->formatValue($value), $sanitizer),
            'value_json' => $valueJson,
            'hit' => $hit,
            'time' => microtime(true),
            'store_name' => $meta['store_name'] ?? null,
            'store_driver' => $meta['store_driver'] ?? null,
            'ttl_seconds' => array_key_exists('ttl_seconds', $meta) ? $meta['ttl_seconds'] : null,
            'expires_at_unix' => array_key_exists('expires_at_unix', $meta) ? $meta['expires_at_unix'] : null,
            'expires_at' => $meta['expires_at'] ?? null,
            'tags' => isset($meta['tags']) && is_array($meta['tags']) ? array_values($meta['tags']) : [],
        ];

        foreach ($meta as $metaKey => $metaValue) {
            if (! array_key_exists($metaKey, $operation)) {
                $operation[$metaKey] = $metaValue;
            }
        }

        $this->operations[] = $operation;
    }

    private function sanitizeFormattedValue(mixed $value, SensitiveDataSanitizer $sanitizer): mixed
    {
        return is_string($value) ? $sanitizer->sanitizeText($value) : $sanitizer->sanitize($value);
    }

    protected function formatValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return strlen($value) > 2000 ? substr($value, 0, 2000) . '…' : $value;
        }

        if (is_array($value) || is_object($value)) {
            $normalized = $this->normalizedForJson($value);
            if ($normalized === null) {
                return '[object ' . (is_object($value) ? get_class($value) : 'array') . ']';
            }
            $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return '[object ' . (is_object($value) ? get_class($value) : 'array') . ']';
            }
            if (is_object($value) && \is_a($value, '\\Phaseolies\\Support\\Collection')) {
                $count = $value->count();
                $first = null;

                try {
                    $first = $value->first();
                } catch (\Throwable) {
                    $first = null;
                }
                $model = is_object($first) ? get_class($first) : 'unknown';

                return "Collection(" . $model . ")[" . $count . "]";
            }

            return strlen($json) > 2000 ? substr($json, 0, 2000) . '…' : $json;
        }

        return $value;
    }

    protected function normalizedForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            if (\is_a($value, '\\Phaseolies\\Support\\Collection')) {
                $first = null;

                try {
                    $first = $value->first();
                } catch (\Throwable) {
                    $first = null;
                }
                $model = is_object($first) ? get_class($first) : null;
                $dataArr = $value->toArray();
                $count = $value->count();

                return [
                    '__type' => 'collection',
                    'model' => $model,
                    'count' => $count,
                    'data' => $dataArr,
                ];
            }
            if (method_exists($value, 'toArray')) {
                try {
                    return $value->toArray();
                } catch (\Throwable) {
                }
            }
            if ($value instanceof \JsonSerializable) {
                try {
                    return $value->jsonSerialize();
                } catch (\Throwable) {
                }
            }
            if ($value instanceof \Traversable) {
                try {
                    return iterator_to_array($value);
                } catch (\Throwable) {
                }
            }

            return (array) $value;
        }

        return null;
    }

    public function toArray(): array
    {
        $hits = 0;
        $misses = 0;
        $writes = 0;
        $deletes = 0;
        $lockOperations = 0;

        foreach ($this->operations as $op) {
            match ($op['type']) {
                'get', 'get_multiple' => $op['hit'] ? $hits++ : $misses++,
                'set', 'forever', 'add', 'set_multiple' => $writes++,
                'delete', 'forget', 'delete_multiple', 'clear' => $deletes++,
                default => null,
            };

            if (str_starts_with((string) $op['type'], 'lock_')) {
                $lockOperations++;
            }
        }

        return [
            'cache_operations' => $this->operations,
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'cache_writes' => $writes,
            'cache_deletes' => $deletes,
            'cache_lock_operations' => $lockOperations,
            'cache_total' => count($this->operations),
        ];
    }
}
