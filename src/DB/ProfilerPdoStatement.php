<?php

namespace Doppar\Insight\DB;

use DateTimeInterface;
use Doppar\Insight\Collectors\SqlCollector;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Database\Database;

class ProfilerPdoStatement extends \PDOStatement
{
    use UsesSensitiveDataSanitizer;

    /** @var array<int|string, mixed> */
    protected array $bound = [];

    protected ?string $connectionName = null;

    protected ?string $driverName = null;

    protected static int $suspendedProfiling = 0;

    // PDO constructs this, must be protected
    protected function __construct(?string $connectionName = null, ?string $driverName = null)
    {
        $this->connectionName = $connectionName;
        $this->driverName = $driverName ? strtolower($driverName) : null;
    }

    public function bindValue($param, $value, $type = \PDO::PARAM_STR): bool
    {
        $this->bound[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    public function bindParam($param, &$var, $type = \PDO::PARAM_STR, $maxLength = null, $driverOptions = null): bool
    {
        // store a snapshot of current value; will be updated at execute() merge
        $this->bound[$param] = $var;

        return parent::bindParam($param, $var, $type, $maxLength ?? 0, $driverOptions ?? null);
    }

    /**
     * @param array<int|string, mixed>|null $input_parameters
     */
    public function execute($input_parameters = null): bool
    {
        $rawBindings = $this->mergeBindings($input_parameters, false);
        $bindings = $this->normalizeBindings($rawBindings);
        $sanitizer = $this->sanitizer();
        $bindingSignature = $this->bindingSignature($bindings);
        $bindings = $sanitizer->sanitizeSqlBindings($bindings);
        $start = microtime(true);
        $ok = false;
        $err = null;

        try {
            // Don't pass empty array if no parameters - let PDO use bindValue() calls
            $ok = $input_parameters === null ? parent::execute() : parent::execute($input_parameters);

            return $ok;
        } catch (\Throwable $e) {
            $err = $e->getMessage();

            throw $e;
        } finally {
            $durationMs = (microtime(true) - $start) * 1000.0;
            $collector = SqlCollector::active();
            if ($collector && self::$suspendedProfiling === 0) {
                $sql = $this->queryString ?? '';
                $rowCount = $this->resolveRowCount($sql, $rawBindings, $ok);
                $collector->registerQuery(
                    $sql,
                    $bindings,
                    $durationMs,
                    $rowCount,
                    $err,
                    $this->connectionName,
                    $this->resolveDriverName(),
                    $this->resolveTransactionState(),
                    $bindingSignature
                );
            }
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function bindingSignature(array $bindings): string
    {
        try {
            return hash('sha256', serialize($bindings));
        } catch (\Throwable) {
            return hash('sha256', json_encode($bindings, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '');
        }
    }

    /**
     * @param mixed $input_parameters
     * @return array<int|string, mixed>
     */
    protected function mergeBindings($input_parameters, bool $normalize = true): array
    {
        $merged = $this->bound;
        if (is_array($input_parameters)) {
            foreach ($input_parameters as $k => $v) {
                $merged[$k] = $v;
            }
        }
        // Normalize numeric keys for positional bindings
        if ($this->hasOnlyNumericKeys($merged)) {
            // ensure order by key asc
            ksort($merged);
            // Don't reindex if keys are already 1-based (PDO positional params start at 1)
            // Only reindex if keys start at 0
            $keys = array_keys($merged);
            if (! empty($keys) && $keys[0] === 0) {
                // Keys start at 0, reindex to maintain order
                $merged = array_values($merged);
            }
            // If keys start at 1, keep them as-is for PDO compatibility
        }

        if ($normalize) {
            return $this->normalizeBindings($merged);
        }

        return $merged;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    protected function normalizeBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            $bindings[$key] = $this->normalizeBinding($value);
        }

        return $bindings;
    }

    /**
     * @param array<int|string, mixed> $arr
     */
    protected function hasOnlyNumericKeys(array $arr): bool
    {
        foreach ($arr as $k => $_) {
            if (! is_int($k)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert non-scalar binding values into safe string representations for logging only.
     *
     * @param mixed $v
     * @return mixed
     */
    protected function normalizeBinding($v): mixed
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        if (is_resource($v)) {
            return 'resource(' . get_resource_type($v) . ')';
        }
        if (is_object($v)) {
            // Avoid invoking __toString implicitly
            return 'object(' . get_class($v) . ')';
        }
        if (is_array($v)) {
            $json = json_encode($v);

            return $json === false ? 'array(' . count($v) . ')' : $json;
        }
        if (is_bool($v)) {
            return $v ? true : false; // keep boolean type
        }

        return $v;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function resolveRowCount(string $sql, array $bindings, bool $executed): ?int
    {
        if (! $executed) {
            return null;
        }

        $nativeRowCount = null;

        try {
            $nativeRowCount = $this->rowCount();
        } catch (\Throwable) {
            $nativeRowCount = null;
        }

        if ($this->shouldUseResultCountFallback($sql, $nativeRowCount)) {
            $fallbackRowCount = $this->countResultRows($sql, $bindings);

            if ($fallbackRowCount !== null) {
                return $fallbackRowCount;
            }
        }

        if ($this->hasUnreliableResultSetRowCount($sql) && ($nativeRowCount === null || $nativeRowCount === 0)) {
            return null;
        }

        return $nativeRowCount;
    }

    protected function shouldUseResultCountFallback(string $sql, ?int $nativeRowCount): bool
    {
        if (! $this->usesUnreliableSelectRowCountDriver()) {
            return false;
        }

        if (! $this->isCountableResultSetQuery($sql)) {
            return false;
        }

        return $nativeRowCount === null || $nativeRowCount === 0;
    }

    protected function hasUnreliableResultSetRowCount(string $sql): bool
    {
        return $this->usesUnreliableSelectRowCountDriver() && $this->isResultSetQuery($sql);
    }

    protected function usesUnreliableSelectRowCountDriver(): bool
    {
        return in_array($this->driverName, ['sqlite', 'pgsql'], true);
    }

    protected function isCountableResultSetQuery(string $sql): bool
    {
        return (bool) preg_match('/^\s*(select|with)\b/i', $sql);
    }

    protected function isResultSetQuery(string $sql): bool
    {
        return (bool) preg_match('/^\s*(select|with|pragma|show|describe|explain)\b/i', $sql);
    }

    protected function resolveDriverName(): ?string
    {
        if (is_string($this->driverName) && $this->driverName !== '') {
            return $this->driverName;
        }

        if ($this->connectionName === null || $this->connectionName === '') {
            return null;
        }

        try {
            $pdo = Database::getPdoInstance($this->connectionName);
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            return is_string($driver) ? strtolower($driver) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveTransactionState(): ?bool
    {
        if ($this->connectionName === null || $this->connectionName === '') {
            return null;
        }

        try {
            return Database::getPdoInstance($this->connectionName)->inTransaction();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function countResultRows(string $sql, array $bindings): ?int
    {
        if ($this->connectionName === null || $this->connectionName === '') {
            return null;
        }

        $normalizedSql = rtrim(trim($sql), "; \t\n\r\0\x0B");
        if ($normalizedSql === '') {
            return null;
        }

        $countSql = "SELECT COUNT(*) AS aggregate FROM ({$normalizedSql}) AS insight_count_subquery";

        self::$suspendedProfiling++;

        try {
            $pdo = Database::getPdoInstance($this->connectionName);
            $statement = $pdo->prepare($countSql);
            $statement->execute($this->prepareBindingsForExecution($bindings));

            $count = $statement->fetchColumn();

            return is_numeric($count) ? (int) $count : null;
        } catch (\Throwable) {
            return null;
        } finally {
            self::$suspendedProfiling = max(0, self::$suspendedProfiling - 1);
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    protected function prepareBindingsForExecution(array $bindings): array
    {
        if (! $this->hasOnlyNumericKeys($bindings)) {
            return $bindings;
        }

        ksort($bindings);

        return array_values($bindings);
    }
}
