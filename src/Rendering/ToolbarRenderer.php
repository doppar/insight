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
            '{{METHOD}}' => $this->escape($data['method'] ?? ''),
            '{{PATH}}' => $this->escape($data['route'] ?? ''),
            '{{DURATION}}' => $this->formatDuration($data),
            '{{SQL_COUNT}}' => (string)($data['sql_total_count'] ?? 0),
            '{{SQL_TIME}}' => number_format($data['sql_total_time_ms'] ?? 0, 1),
            '{{LOGS_COUNT}}' => (string)($data['logs_total_count'] ?? 0),
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
        if (!is_file($logoPath)) {
            return '';
        }

        $imageData = base64_encode(file_get_contents($logoPath));
        return 'data:image/png;base64,' . $imageData;
    }
}
