<?php

declare(strict_types=1);

namespace Doppar\Insight\Storage;

use Doppar\Insight\Contracts\StorageInterface;
use JsonException;

class FileStorage implements StorageInterface
{
    private const CLEANUP_INTERVAL = 86400; // Run cleanup every day
    private const SECONDS_PER_DAY = 86400;

    protected string $baseDir;

    public function __construct(
        ?string $baseDir = null,
        private readonly int $retentionDays = 1,
        private readonly int $maxProfileBytes = 1048576,
        private readonly int $maxStorageBytes = 104857600
    ) {
        $this->baseDir = $baseDir ?? rtrim(storage_path('framework/profiler'), DIRECTORY_SEPARATOR);
    }

    protected function dir(): string
    {
        return $this->baseDir;
    }

    protected function cleanupMarkerPath(): string
    {
        return $this->dir() . DIRECTORY_SEPARATOR . '.cleanup-marker';
    }

    public function put(string $id, array $data): void
    {
        try {
            $dir = $this->dir();

            if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                return;
            }

            $json = $this->encodeProfile($id, $data);
            if ($json === null) {
                return;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $id . '.json';
            $temporaryPath = tempnam($dir, '.insight-');
            if ($temporaryPath === false) {
                return;
            }

            try {
                $written = file_put_contents($temporaryPath, $json, LOCK_EX);
                if ($written !== strlen($json) || ! rename($temporaryPath, $path)) {
                    return;
                }
            } finally {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }

            $this->cleanupOldFiles();
        } catch (\Throwable) {
            // Insight persistence must never interrupt the profiled request.
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeProfile(string $id, array $data): ?string
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            $limit = max(1024, $this->maxProfileBytes);

            if (strlen($json) <= $limit) {
                return $json;
            }

            $compact = $this->compactValue($data);
            $compact['_insight_truncated'] = true;
            $json = json_encode($compact, JSON_THROW_ON_ERROR);

            if (strlen($json) <= $limit) {
                return $json;
            }

            return json_encode([
                'id' => substr($id, 0, 128),
                'method' => substr((string) ($data['method'] ?? ''), 0, 16),
                'route' => substr((string) ($data['route'] ?? '/'), 0, 256),
                'status' => (int) ($data['status'] ?? 0),
                'duration_ms' => (float) ($data['duration_ms'] ?? 0),
                '_insight_truncated' => true,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function compactValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 8) {
            return '[truncated]';
        }

        if (is_string($value)) {
            return strlen($value) > 4096 ? substr($value, 0, 4096) . '...[truncated]' : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $compact = [];
        foreach (array_slice($value, 0, 100, true) as $key => $item) {
            $compact[$key] = $this->compactValue($item, $depth + 1);
        }

        if (count($value) > 100) {
            $compact['_insight_truncated_items'] = count($value) - 100;
        }

        return $compact;
    }

    public function get(string $id): ?array
    {
        $path = $this->dir() . DIRECTORY_SEPARATOR . $id . '.json';

        if (! is_file($path)) {
            return null;
        }

        return $this->decodeFile($path);
    }

    public function recent(int $limit = 50): array
    {
        $dir = $this->dir();

        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $limit = max(1, $limit);
        $profiles = [];

        foreach ($files as $file) {
            $data = $this->decodeFile($file);
            if (! is_array($data)) {
                continue;
            }

            $profiles[] = $this->summarizeProfile($file, $data);
        }

        usort($profiles, function (array $left, array $right): int {
            $leftTimestamp = (int) ($left['captured_at_unix'] ?? 0);
            $rightTimestamp = (int) ($right['captured_at_unix'] ?? 0);

            return $rightTimestamp <=> $leftTimestamp;
        });

        return array_slice($profiles, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeFile(string $path): ?array
    {
        $json = file_get_contents($path);

        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function summarizeProfile(string $path, array $data): array
    {
        $timestamp = $this->resolveTimestamp($path, $data);
        $route = (string) ($data['route'] ?? $data['request_server']['PATH'] ?? $data['url'] ?? '/');
        $method = strtoupper((string) ($data['method'] ?? $data['request_server']['METHOD'] ?? 'GET'));

        if ($route === '') {
            $route = '/';
        }

        return [
            'id' => (string) ($data['id'] ?? pathinfo($path, PATHINFO_FILENAME)),
            'method' => $method !== '' ? $method : 'GET',
            'route' => $route,
            'status' => (int) ($data['status'] ?? $data['response_status'] ?? 0),
            'duration_ms' => (float) ($data['total_duration_ms'] ?? $data['duration_ms'] ?? 0),
            'exception_class' => isset($data['exception_class']) ? (string) $data['exception_class'] : null,
            'exception_message' => isset($data['exception_message']) ? (string) $data['exception_message'] : null,
            'captured_at' => $timestamp > 0 ? gmdate(DATE_ATOM, $timestamp) : null,
            'captured_at_unix' => $timestamp,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveTimestamp(string $path, array $data): int
    {
        $timeStart = $data['time_start'] ?? null;

        if (is_numeric($timeStart)) {
            return (int) floor((float) $timeStart);
        }

        return $this->fileTimestamp($path);
    }

    private function fileTimestamp(string $path): int
    {
        $timestamp = filemtime($path);

        return $timestamp !== false ? $timestamp : 0;
    }

    /**
     * Clean up JSON files older than the retention period.
     * Only runs once per cleanup interval to avoid performance impact.
     */
    private function cleanupOldFiles(): void
    {
        $now = time();
        $markerPath = $this->cleanupMarkerPath();
        $lastCleanupTime = is_file($markerPath) ? (int) (filemtime($markerPath) ?: 0) : 0;

        if ($lastCleanupTime > 0 && ($now - $lastCleanupTime) < self::CLEANUP_INTERVAL) {
            return;
        }
        $dir = $this->dir();

        if (! is_dir($dir)) {
            return;
        }

        $cutoffTime = $now - ($this->retentionDays * self::SECONDS_PER_DAY);
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];

        foreach ($files as $file) {
            $mtime = filemtime($file);

            if ($mtime !== false && $mtime < $cutoffTime) {
                @unlink($file);
            }
        }

        $this->enforceStorageQuota();
        @touch($markerPath, $now);
    }

    private function enforceStorageQuota(): void
    {
        if ($this->maxStorageBytes <= 0) {
            return;
        }

        $files = glob($this->dir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
        usort(
            $files,
            static fn (string $left, string $right): int =>
                (int) (filemtime($left) ?: 0) <=> (int) (filemtime($right) ?: 0)
        );

        $sizes = [];
        $total = 0;
        foreach ($files as $file) {
            $size = filesize($file);
            if ($size === false) {
                continue;
            }

            $sizes[$file] = $size;
            $total += $size;
        }

        foreach ($files as $file) {
            if ($total <= $this->maxStorageBytes) {
                break;
            }

            if (! isset($sizes[$file]) || ! @unlink($file)) {
                continue;
            }

            $total -= $sizes[$file];
        }
    }
}
