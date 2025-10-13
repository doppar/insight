<?php

declare(strict_types=1);

namespace Doppar\Insight\Storage;

use Doppar\Insight\Contracts\StorageInterface;
use JsonException;

class FileStorage implements StorageInterface
{
    private int $lastCleanupTime = 0;
    private const CLEANUP_INTERVAL = 86400; // Run cleanup every day
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        protected ?string $baseDir = null,
        private readonly int $retentionDays = 1
    ) {
        $this->baseDir ??= rtrim(storage_path('framework/profiler'), DIRECTORY_SEPARATOR);
    }

    protected function dir(): string
    {
        return $this->baseDir;
    }

    public function put(string $id, array $data): void
    {
        $dir = $this->dir();
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $id . '.json';
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        
        $this->cleanupOldFiles();
    }

    public function get(string $id): ?array
    {
        $path = $this->dir() . DIRECTORY_SEPARATOR . $id . '.json';
        
        if (!is_file($path)) {
            return null;
        }
        
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
     * Clean up JSON files older than the retention period.
     * Only runs once per cleanup interval to avoid performance impact.
     */
    private function cleanupOldFiles(): void
    {
        $now = time();
        
        if ($now - $this->lastCleanupTime < self::CLEANUP_INTERVAL) {
            return;
        }
        
        $this->lastCleanupTime = $now;
        $dir = $this->dir();
        
        if (!is_dir($dir)) {
            return;
        }

        $cutoffTime = $now - ($this->retentionDays * self::SECONDS_PER_DAY);
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            
            if ($mtime !== false && $mtime < $cutoffTime) {
                unlink($file);
            }
        }
    }
}
