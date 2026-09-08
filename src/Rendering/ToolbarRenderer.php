<?php

namespace Doppar\Insight\Rendering;

/**
 * Renders the profiler toolbar HTML
 */
class ToolbarRenderer
{
    /**
     * Render the toolbar with profiler data
     *
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        $template = $this->loadTemplate();

        if ($template === '') {
            return '';
        }

        $replacements = $this->buildReplacements($data);

        return strtr($template, $replacements);
    }

    /**
     * Load the toolbar template
     */
    protected function loadTemplate(): string
    {
        $stubPath = __DIR__ . '/../../resources/stubs/toolbar.html';
        $template = is_file($stubPath) ? file_get_contents($stubPath) : '';

        return $template ?: '';
    }

    /**
     * Build template replacements from profiler data
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function buildReplacements(array $data): array
    {
        return [
            '{{CSS}}' => $this->inlineCss(),
            '{{JS}}' => $this->inlineJs(),
            '{{LOGO}}' => $this->getLogo(),
            '{{ID}}' => $this->escape($data['id'] ?? ''),
            '{{STATUS}}' => (string)($data['status'] ?? 0),
            '{{STATUS_CLASS}}' => $this->statusClass($data),
            '{{METHOD}}' => $this->escape($data['method'] ?? ''),
            '{{METHOD_CLASS}}' => $this->methodClass((string) ($data['method'] ?? '')),
            '{{PATH}}' => $this->escape($data['route'] ?? ''),
            '{{PATH_DISPLAY}}' => $this->escape($this->formatPathDisplay((string) ($data['route'] ?? ''))),
            '{{DURATION}}' => $this->formatDuration($data),
            '{{MEMORY_PEAK}}' => $this->formatMemoryPeak($data),
            '{{SQL_COUNT}}' => (string)($data['sql_total_count'] ?? 0),
            '{{SQL_TIME}}' => number_format($data['sql_total_time_ms'] ?? 0, 1),
            '{{SQL_SUMMARY}}' => $this->formatSqlSummary($data),
            '{{CACHE_SUMMARY}}' => $this->formatCacheSummary($data),
            '{{LOGS_COUNT}}' => (string)($data['logs_total_count'] ?? 0),
            '{{SESSION_COUNT}}' => (string)count((array)($data['session_data'] ?? [])),
            '{{RESPONSE_SIZE}}' => $this->formatResponseSize($data),
            '{{FRAMEWORK_VERSION}}' => $this->escape($data['framework_version'] ?? 'unknown'),
            '{{PHP_VERSION}}' => $this->escape($data['php_version'] ?? PHP_VERSION),
            '{{IS_REDIRECT}}' => ($data['is_redirect'] ?? false) ? 'true' : 'false',
            '{{REDIRECT_URL}}' => $this->escape($data['redirect_url'] ?? ''),
            '{{REDIRECT_CHAIN}}' => $this->escapeJson($data['redirect_chain'] ?? []),
            '{{AUTH_AUTHENTICATED}}' => ($data['auth_authenticated'] ?? false) ? 'true' : 'false',
            '{{AUTH_USER_NAME}}' => $this->escape($data['auth_user_name'] ?? 'Guest'),
            '{{AUTH_USER_EMAIL}}' => $this->escape($data['auth_user_email'] ?? ''),
        ];
    }

    /**
     * Format duration from profiler data
     *
     * @param array<string, mixed> $data
     */
    protected function formatDuration(array $data): string
    {
        $duration = $data['total_duration_ms'] ?? $data['duration_ms'] ?? 0;

        return number_format($duration, 1);
    }

    /**
     * Format peak memory from profiler data
     *
     * @param array<string, mixed> $data
     */
    protected function formatMemoryPeak(array $data): string
    {
        $bytes = (float) ($data['memory_peak'] ?? 0);

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return (int) $bytes . ' B';
    }

    /**
     * Format response body size from profiler data
     *
     * @param array<string, mixed> $data
     */
    protected function formatResponseSize(array $data): string
    {
        $bytes = (int) ($data['response_body_size'] ?? 0);

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Format cache activity summary from profiler data
     *
     * @param array<string, mixed> $data
     */
    protected function formatCacheSummary(array $data): string
    {
        return (string) (int) ($data['cache_total'] ?? 0);
    }

    /**
     * Compact SQL summary for toolbar display
     *
     * @param array<string, mixed> $data
     */
    protected function formatSqlSummary(array $data): string
    {
        return (string) (int) ($data['sql_total_count'] ?? 0);
    }

    protected function formatPathDisplay(string $path, int $maxLength = 54): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        if (mb_strlen($path) <= $maxLength) {
            return $path;
        }

        $head = 28;
        $tail = 18;

        return mb_substr($path, 0, $head) . '...' . mb_substr($path, -$tail);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function statusClass(array $data): string
    {
        $status = (int) ($data['status'] ?? 0);

        if ($status >= 500) {
            return 'dp-status-5xx';
        }

        if ($status >= 400) {
            return 'dp-status-4xx';
        }

        if ($status >= 300) {
            return 'dp-status-3xx';
        }

        if ($status >= 200) {
            return 'dp-status-2xx';
        }

        if ($status >= 100) {
            return 'dp-status-1xx';
        }

        return '';
    }

    protected function methodClass(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'dp-method-get',
            'POST' => 'dp-method-post',
            'PUT' => 'dp-method-put',
            'PATCH' => 'dp-method-patch',
            'DELETE' => 'dp-method-delete',
            'OPTIONS' => 'dp-method-options',
            'HEAD' => 'dp-method-head',
            default => '',
        };
    }

    /**
     * Escape HTML string
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape and encode JSON data
     *
     * @param array<int|string, mixed> $data
     */
    protected function escapeJson(array $data): string
    {
        $json = json_encode($data);

        return htmlspecialchars($json ?: '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Inline CSS content
     */
    protected function inlineCss(): string
    {
        $path = __DIR__ . '/../../resources/assets/toolbar.css';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Inline JavaScript content
     */
    protected function inlineJs(): string
    {
        $path = __DIR__ . '/../../resources/assets/toolbar.js';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Get logo as base64 data URI
     */
    protected function getLogo(): string
    {
        $logoPath = __DIR__ . '/../../resources/assets/logo.png';
        if (! is_file($logoPath)) {
            return '';
        }

        $content = file_get_contents($logoPath);
        if ($content === false) {
            return '';
        }

        $imageData = base64_encode($content);

        return 'data:image/png;base64,' . $imageData;
    }
}
