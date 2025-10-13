<?php

namespace Doppar\Insight\DB;

use Doppar\Insight\Collectors\SqlCollector;
use DateTimeInterface;

class ProfilerPdoStatement extends \PDOStatement
{
    /** @var array<int|string, mixed> */
    protected array $bound = [];

    // PDO constructs this, must be protected
    protected function __construct()
    {
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

    public function execute($input_parameters = null): bool
    {
        $bindings = $this->mergeBindings($input_parameters);
        $start = microtime(true);
        $ok = false; $err = null;
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
            if ($collector) {
                $sql = $this->queryString ?? '';
                $rowCount = null;
                try { $rowCount = $this->rowCount(); } catch (\Throwable) { /* ignore */ }
                $collector->registerQuery($sql, $bindings, $durationMs, $rowCount, $err);
            }
        }
    }

    /**
     * @param mixed $input_parameters
     * @return array<int|string, mixed>
     */
    protected function mergeBindings($input_parameters): array
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
            if (!empty($keys) && $keys[0] === 0) {
                // Keys start at 0, reindex to maintain order
                $merged = array_values($merged);
            }
            // If keys start at 1, keep them as-is for PDO compatibility
        }
        // Normalize values for safe logging (do not mutate values passed to PDO)
        foreach ($merged as $k => $v) {
            $merged[$k] = $this->normalizeBinding($v);
        }
        return $merged;
    }

    protected function hasOnlyNumericKeys(array $arr): bool
    {
        foreach ($arr as $k => $_) {
            if (!is_int($k)) return false;
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
}
