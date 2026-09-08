<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class SqlCollector implements CollectorInterface
{
    use UsesSensitiveDataSanitizer;

    /** @var array<int, array<string, mixed>> */
    protected array $queries = [];

    protected float $totalTimeMs = 0.0;

    protected float $slowThresholdMs = 100.0;

    protected int $nPlusOneThreshold = 3;

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
        $this->slowThresholdMs = (float) $this->readConfig('insight.sql_slow_threshold_ms', 100);
        $this->nPlusOneThreshold = max(2, (int) $this->readConfig('insight.sql_n_plus_one_threshold', 3));
        self::setActive($this);
    }

    public function stop(Request $request, Response $response): void
    {
        self::setActive(null);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function registerQuery(
        string $sql,
        array $bindings,
        float $durationMs,
        ?int $rowCount = null,
        ?string $error = null,
        ?string $connectionName = null,
        ?string $driverName = null,
        ?bool $transactionActive = null,
        ?string $bindingSignature = null
    ): void {
        $sanitizer = $this->sanitizer();
        $bindingSignature ??= $this->bindingSignature($bindings);
        $bindings = $sanitizer->sanitizeSqlBindings($bindings);

        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'binding_signature' => $bindingSignature,
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'error' => $error,
            'connection_name' => $connectionName,
            'driver_name' => $driverName,
            'transaction_active' => $transactionActive,
            'query_type' => $this->detectQueryType($sql),
            'is_slow' => $durationMs >= $this->slowThresholdMs,
        ];
        $this->totalTimeMs += $durationMs;
    }

    public function toArray(): array
    {
        [$enrichedQueries, $summary] = $this->enrichQueries($this->queries);

        $maxList = 50;
        $list = count($enrichedQueries) > $maxList ? array_slice($enrichedQueries, 0, $maxList) : $enrichedQueries;

        return [
            'sql_total_count' => count($this->queries),
            'sql_total_time_ms' => $this->totalTimeMs,
            'sql_slow_threshold_ms' => $this->slowThresholdMs,
            'sql_slow_count' => $summary['slow_count'],
            'sql_duplicate_group_count' => $summary['duplicate_group_count'],
            'sql_duplicate_total_count' => $summary['duplicate_total_count'],
            'sql_n_plus_one_count' => count($summary['n_plus_one_hints']),
            'sql_n_plus_one_hints' => $summary['n_plus_one_hints'],
            'sql' => $list,
        ];
    }

    protected function detectQueryType(string $sql): string
    {
        if (preg_match('/^\s*([a-z]+)/i', $sql, $matches) !== 1) {
            return 'unknown';
        }

        return strtolower($matches[1]);
    }

    protected function readConfig(string $key, mixed $default): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function normalizeFingerprint(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim(strtolower($sql)));

        return $normalized === null ? strtolower(trim($sql)) : $normalized;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function bindingSignature(array $bindings): string
    {
        $encoded = json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($encoded !== false) {
            return $encoded;
        }

        try {
            return hash('sha256', serialize($bindings));
        } catch (\Throwable) {
            return hash('sha256', implode('|', array_map('get_debug_type', $bindings)));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $queries
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    protected function enrichQueries(array $queries): array
    {
        $fingerprints = [];
        $bindingVariants = [];

        foreach ($queries as $index => $query) {
            $fingerprint = $this->normalizeFingerprint((string) ($query['sql'] ?? ''));
            $fingerprints[$index] = $fingerprint;
            $bindingVariants[$fingerprint][$query['binding_signature'] ?? $this->bindingSignature((array) ($query['bindings'] ?? []))] = true;
        }

        $counts = array_count_values($fingerprints);
        $occurrenceOrder = [];
        $duplicateGroupCount = 0;
        $duplicateTotalCount = 0;
        $slowCount = 0;
        $nPlusOneGroups = [];
        $enriched = [];

        foreach ($queries as $index => $query) {
            $fingerprint = $fingerprints[$index];
            $duplicateCount = $counts[$fingerprint] ?? 1;
            $occurrenceOrder[$fingerprint] = ($occurrenceOrder[$fingerprint] ?? 0) + 1;
            $bindingVariantCount = count($bindingVariants[$fingerprint] ?? []);
            $queryType = (string) ($query['query_type'] ?? 'unknown');
            $nPlusOneSuspected = in_array($queryType, ['select', 'with'], true)
                && $duplicateCount >= $this->nPlusOneThreshold
                && $bindingVariantCount > 1;

            if (($query['is_slow'] ?? false) === true) {
                $slowCount++;
            }

            if ($duplicateCount > 1 && $occurrenceOrder[$fingerprint] === 1) {
                $duplicateGroupCount++;
                $duplicateTotalCount += $duplicateCount;
            }

            if ($nPlusOneSuspected && ! isset($nPlusOneGroups[$fingerprint])) {
                $nPlusOneGroups[$fingerprint] = [
                    'sql' => $query['sql'],
                    'query_type' => $queryType,
                    'duplicate_count' => $duplicateCount,
                    'binding_variant_count' => $bindingVariantCount,
                    'connection_name' => $query['connection_name'] ?? null,
                    'driver_name' => $query['driver_name'] ?? null,
                ];
            }

            unset($query['binding_signature']);
            $query['duplicate_count'] = $duplicateCount;
            $query['duplicate_index'] = $occurrenceOrder[$fingerprint];
            $query['binding_variant_count'] = $bindingVariantCount;
            $query['n_plus_one_suspected'] = $nPlusOneSuspected;
            $query['fingerprint'] = sha1($fingerprint);
            $enriched[] = $query;
        }

        return [
            $enriched,
            [
                'slow_count' => $slowCount,
                'duplicate_group_count' => $duplicateGroupCount,
                'duplicate_total_count' => $duplicateTotalCount,
                'n_plus_one_hints' => array_values($nPlusOneGroups),
            ],
        ];
    }
}
