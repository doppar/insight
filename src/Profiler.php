<?php

namespace Doppar\Insight;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Contracts\StorageInterface;

class Profiler
{
    protected array $config;

    protected float $startTime = 0.0;
    protected int $startMemory = 0;

    protected string $requestId = '';
    protected array $data = [];

    // Simple in-memory storage per request
    protected static array $storage = [];

    /**
     * @var array<int, CollectorInterface>
     */
    protected array $collectors = [];

    protected StorageInterface $storageDriver;

    public function __construct(array $config = [], ?StorageInterface $storage = null)
    {
        $this->config = array_merge([
            'enabled' => null, // null => auto by env
            'allow_ips' => ['127.0.0.1', '::1'],
            'collect' => [
                'db' => false,
                'logs' => false,
                'cache' => false,
            ],
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
        if (!$this->isGloballyEnabled()) {
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

        // Store in-memory for same-request usage
        self::$storage[$this->requestId] = $this->data;

        // Persist via storage driver for cross-request retrieval
        $this->storageDriver->put($this->requestId, $this->data);
    }

    public function getData(string $id): ?array
    {
        // First, check in-memory (same request)
        if (isset(self::$storage[$id])) {
            return self::$storage[$id];
        }

        // Delegate to storage driver
        return $this->storageDriver->get($id);
    }

    public function getCurrentData(): array
    {
        return $this->data;
    }

    public function getRedirectChain(): array
    {
        if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
            $chain = $_SESSION['_profiler_redirect_chain'] ?? [];
            // Clear the chain after retrieving it
            unset($_SESSION['_profiler_redirect_chain']);
            return $chain;
        }
        return [];
    }

    public function shouldInject(Response $response): bool
    {
        if ($response->isEmpty()) return false;
        $code = $response->getStatusCode();
        if ($code >= 300 && $code < 400) return false;

        $ct = strtolower($response->headers->get('Content-Type') ?? '');
        if ($ct && !str_contains($ct, 'text/html')) return false;

        $disp = strtolower($response->headers->get('Content-Disposition') ?? '');
        if ($disp && str_contains($disp, 'attachment')) return false;

        // Avoid calling getBody() (strict string); use public property which may be empty
        $body = $response->body ?? '';
        if ($body === '') return false;

        return true;
    }

    public function renderToolbar(): string
    {
        $d = $this->data;
        $duration = number_format($d['duration_ms'] ?? 0, 1);
        $memMb = number_format(($d['memory_peak'] ?? 0) / (1024*1024), 2);
        $status = (int)($d['status'] ?? 0);
        $method = htmlspecialchars($d['method'] ?? '', ENT_QUOTES, 'UTF-8');
        $path = htmlspecialchars($d['route'] ?? '', ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($d['id'] ?? '', ENT_QUOTES, 'UTF-8');
        $sqlCount = (int)($d['sql_total_count'] ?? 0);
        $sqlTime = number_format($d['sql_total_time_ms'] ?? 0, 1);
        
        // Check for redirect
        $isRedirect = ($d['is_redirect'] ?? false) ? 'true' : 'false';
        $redirectUrl = htmlspecialchars($d['redirect_url'] ?? '', ENT_QUOTES, 'UTF-8');
        
        // Get redirect chain from previous requests
        $redirectChain = $this->getRedirectChain();
        $redirectChainJson = json_encode($redirectChain);

        $css = $this->inlineCss();
        $js = $this->inlineJs();

        $stubPath = __DIR__ . '/../resources/stubs/toolbar.html';
        $template = is_file($stubPath) ? file_get_contents($stubPath) : '';

        if ($template === '') {
            return '';
        }

        $frameworkVersion = htmlspecialchars($d['framework_version'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $phpVersion = htmlspecialchars($d['php_version'] ?? PHP_VERSION, ENT_QUOTES, 'UTF-8');
        
        $replacements = [
            '{{CSS}}' => $css,
            '{{JS}}' => $js,
            '{{ID}}' => $id,
            '{{STATUS}}' => (string)$status,
            '{{METHOD}}' => $method,
            '{{PATH}}' => $path,
            '{{DURATION}}' => $duration,
            '{{SQL_COUNT}}' => (string)$sqlCount,
            '{{SQL_TIME}}' => $sqlTime,
            '{{FRAMEWORK_VERSION}}' => $frameworkVersion,
            '{{PHP_VERSION}}' => $phpVersion,
            '{{IS_REDIRECT}}' => $isRedirect,
            '{{REDIRECT_URL}}' => $redirectUrl,
            '{{REDIRECT_CHAIN}}' => htmlspecialchars($redirectChainJson, ENT_QUOTES, 'UTF-8'),
        ];

        return strtr($template, $replacements);
    }

    protected function inlineCss(): string
    {
        $path = __DIR__ . '/../resources/assets/toolbar.css';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    protected function inlineJs(): string
    {
        $path = __DIR__ . '/../resources/assets/toolbar.js';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
