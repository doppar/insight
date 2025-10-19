<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class LogCollector implements CollectorInterface
{
    /** @var array<int, array<string, mixed>> */
    protected array $logs = [];

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
        return 'logs';
    }

    public function start(Request $request): void
    {
        $this->logs = [];
        self::setActive($this);
    }

    public function stop(Request $request, Response $response): void
    {
        self::setActive(null);
    }

    /**
     * Register a log entry
     *
     * @param string $level The log level (debug, info, notice, warning, error, critical, alert, emergency)
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @param float $timestamp The timestamp when the log was created
     * @return void
     */
    public function registerLog(string $level, string $message, array $context = [], float $timestamp = 0.0): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => $timestamp ?: microtime(true),
            'time' => date('H:i:s', (int)($timestamp ?: microtime(true))),
        ];
    }

    public function toArray(): array
    {
        // Limit the list size in payload to avoid huge responses
        $maxList = 100;
        $list = count($this->logs) > $maxList ? array_slice($this->logs, 0, $maxList) : $this->logs;

        return [
            'logs_total_count' => count($this->logs),
            'logs' => $list,
        ];
    }
}
