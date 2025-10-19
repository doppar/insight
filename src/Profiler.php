<?php

namespace Doppar\Insight;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Contracts\StorageInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class Profiler
{
    /** @var array<string, mixed> */
    protected array $config;

    protected float $startTime = 0.0;
    protected int $startMemory = 0;

    protected string $requestId = '';
    /** @var array<string, mixed> */
    protected array $data = [];

    // Simple in-memory storage per request
    /** @var array<string, array<string, mixed>> */
    protected static array $storage = [];

    /**
     * @var array<int, CollectorInterface>
     */
    protected array $collectors = [];

    protected StorageInterface $storageDriver;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?StorageInterface $storage = null)
    {
        $this->config = array_merge([
            'enabled' => null, // null => auto by env
            'allow_ips' => ['127.0.0.1', '::1'],
        ], $config);

        // Default storage driver can be provided via Service Provider
        $this->storageDriver = $storage ?? new \Doppar\Insight\Storage\FileStorage();
    }

    public function isGloballyEnabled(): bool
    {
        $envEnabled = app()->isDevelopment();

        return ($this->config['enabled'] ?? null) === null
            ? $envEnabled
            : (bool) $this->config['enabled'];
    }

    public function addCollector(CollectorInterface $collector): void
    {
        $this->collectors[] = $collector;
    }

    public function isEnabledFor(Request $request): bool
    {
        if (! $this->isGloballyEnabled()) {
            return false;
        }
        $ip = $request->ip();

        return in_array($ip, $this->config['allow_ips'] ?? [], true);
    }

    public function start(Request $request): void
    {
        $this->requestId = bin2hex(random_bytes(8));
        // Start at PHP's request start time if available to cover total request duration
        $this->startTime = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);
        $this->startMemory = memory_get_usage(true);

        // Prime base data
        $this->data = [
            'id' => $this->requestId,
        ];

        // Start all collectors
        foreach ($this->collectors as $collector) {
            $collector->start($request);
        }
    }

    public function stop(Request $request, Response $response): void
    {
        // Stop collectors and aggregate
        foreach ($this->collectors as $collector) {
            $collector->stop($request, $response);
            $this->data = array_merge($this->data, $collector->toArray());
        }

        // Also ensure common fields when no collectors provide them
        $this->data += [
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type') ?? '',
        ];

        // Get and store redirect chain with the profiler data (don't clear session yet)
        $redirectChain = $this->getRedirectChain(false);
        if (! empty($redirectChain)) {
            $this->data['redirect_chain'] = $redirectChain;

            // Calculate total duration including all redirects
            $totalDuration = $this->data['duration_ms'] ?? 0;
            foreach ($redirectChain as $redirect) {
                $totalDuration += $redirect['duration_ms'] ?? 0;
            }
            $this->data['total_duration_ms'] = $totalDuration;
            $this->data['redirect_count'] = count($redirectChain);
        }

        // Store in-memory for same-request usage
        self::$storage[$this->requestId] = $this->data;

        // Persist via storage driver for cross-request retrieval
        $this->storageDriver->put($this->requestId, $this->data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(string $id): ?array
    {
        // First, check in-memory (same request)
        if (isset(self::$storage[$id])) {
            return self::$storage[$id];
        }

        // Delegate to storage driver
        return $this->storageDriver->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrentData(): array
    {
        return $this->data;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRedirectChain(bool $clear = false): array
    {
        if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
            $chain = $_SESSION['_insight_redirect_chain'] ?? [];
            // Clear the chain only if requested
            if ($clear) {
                unset($_SESSION['_insight_redirect_chain']);
            }

            return $chain;
        }

        return [];
    }

    public function shouldInject(Response $response): bool
    {
        if ($response->isEmpty()) {
            return false;
        }
        $code = $response->getStatusCode();
        if ($code >= 300 && $code < 400) {
            return false;
        }

        $ct = strtolower($response->headers->get('Content-Type') ?? '');
        if ($ct && ! str_contains($ct, 'text/html')) {
            return false;
        }

        $disp = strtolower($response->headers->get('Content-Disposition') ?? '');
        if ($disp && str_contains($disp, 'attachment')) {
            return false;
        }

        // Avoid calling getBody() (strict string); use public property which may be empty
        $body = $response->body ?? '';
        if ($body === '') {
            return false;
        }

        return true;
    }

    /**
     * Render the profiler toolbar HTML
     */
    public function renderToolbar(): string
    {
        $renderer = new \Doppar\Insight\Rendering\ToolbarRenderer();

        return $renderer->render($this->data);
    }
}
