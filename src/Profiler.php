<?php

namespace Doppar\Insight;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Contracts\StorageInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Throwable;

class Profiler
{
    /** @var array<string, mixed> */
    protected array $config;

    protected float $startTime = 0.0;
    protected int $startMemory = 0;

    protected string $requestId = '';
    /** @var array<string, mixed> */
    protected array $data = [];
    protected bool $started = false;
    protected bool $stopped = false;

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
            'allow_production' => false,
            'allow_ips' => ['127.0.0.1', '::1'],
        ], $config);

        // Default storage driver can be provided via Service Provider
        $this->storageDriver = $storage ?? new \Doppar\Insight\Storage\FileStorage();
    }

    public function isGloballyEnabled(): bool
    {
        if ($this->isProductionEnvironment() && empty($this->config['allow_production'])) {
            return false;
        }

        if (($this->config['enabled'] ?? null) !== null) {
            return (bool) $this->config['enabled'];
        }

        return app()->isDevelopment();
    }

    protected function isProductionEnvironment(): bool
    {
        try {
            return app()->isProduction();
        } catch (Throwable) {
            return false;
        }
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
        if ($this->started && ! $this->stopped) {
            return;
        }

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
        $this->started = true;
        $this->stopped = false;

        // Start all collectors
        foreach ($this->collectors as $collector) {
            $collector->start($request);
        }
    }

    public function stop(Request $request, Response $response): void
    {
        $this->finalize($request, $response);
    }

    public function stopWithException(Request $request, Throwable $exception, ?Response $response = null): void
    {
        if (! $this->started || $this->stopped) {
            return;
        }

        $statusCode = $this->resolveExceptionStatus($exception);
        $response ??= new Response('', $statusCode);
        $response->setException($exception);

        if (method_exists($exception, 'getHeaders')) {
            $headers = $exception->getHeaders();
            if (is_array($headers) && $headers !== []) {
                $response->withHeaders($headers);
            }
        }

        $this->data['exception_class'] = $exception::class;
        $this->data['exception_message'] = $exception->getMessage();

        $this->finalize($request, $response);
    }

    public function isRunning(): bool
    {
        return $this->started && ! $this->stopped;
    }

    public function hasStopped(): bool
    {
        return $this->stopped;
    }

    public function discard(): void
    {
        $this->data = [];
        $this->requestId = '';
        $this->startTime = 0.0;
        $this->startMemory = 0;
        $this->started = false;
        $this->stopped = false;
    }

    protected function finalize(Request $request, Response $response): void
    {
        if (! $this->started || $this->stopped) {
            return;
        }

        // Stop collectors and aggregate
        foreach ($this->collectors as $collector) {
            $collector->stop($request, $response);
            $this->data = array_merge($this->data, $collector->toArray());
        }

        // Also ensure common fields when no collectors provide them
        $this->data += [
            'method' => strtoupper((string) $request->getMethod()),
            'route' => $request->getPath() !== '' ? $request->getPath() : '/',
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
        try {
            $this->storageDriver->put($this->requestId, $this->data);
        } catch (Throwable) {
            // Insight persistence must never interrupt the profiled request.
        }

        $this->stopped = true;
        $this->started = false;
    }

    protected function resolveExceptionStatus(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = (int) $exception->getStatusCode();
            if ($statusCode >= 400 && $statusCode < 600) {
                return $statusCode;
            }
        }

        $code = (int) $exception->getCode();

        return ($code >= 400 && $code < 600) ? $code : 500;
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
     * @return array<int, array<string, mixed>>
     */
    public function getRecentRequests(int $limit = 50): array
    {
        return $this->storageDriver->recent($limit);
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
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
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
