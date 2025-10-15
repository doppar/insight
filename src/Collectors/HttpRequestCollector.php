<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class HttpRequestCollector implements CollectorInterface
{
    protected array $requests = [];
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
        return 'http_requests';
    }

    public function start(Request $request): void
    {
        $this->requests = [];
        self::setActive($this);
    }

    public function stop(Request $request, Response $response): void
    {
        self::setActive(null);
    }

    /**
     * Register an outgoing HTTP request
     */
    public function registerRequest(string $method, string $url, float $duration, ?int $status, bool $successful): void
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'duration_ms' => $duration,
            'status' => $status,
            'successful' => $successful,
        ];
    }

    public function toArray(): array
    {
        $totalTime = 0;
        foreach ($this->requests as $req) {
            $totalTime += $req['duration_ms'] ?? 0;
        }

        return [
            'http_requests' => $this->requests,
            'http_requests_count' => count($this->requests),
            'http_requests_total_time_ms' => $totalTime,
        ];
    }
}
