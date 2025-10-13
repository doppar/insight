<?php

namespace Doppar\Insight\Storage;

use Doppar\Insight\Contracts\StorageInterface;

class FileStorage implements StorageInterface
{
    public function __construct(
        protected ?string $baseDir = null
    ) {
        $this->baseDir = $this->baseDir ?: rtrim(storage_path('framework/profiler'), DIRECTORY_SEPARATOR);
    }

    protected function dir(): string
    {
        return $this->baseDir;
    }

    public function put(string $id, array $data): void
    {
        $dir = $this->dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $id . '.json';
        @file_put_contents($path, json_encode($data));
    }

    public function get(string $id): ?array
    {
        $path = $this->dir() . DIRECTORY_SEPARATOR . $id . '.json';
        if (!is_file($path)) {
            return null;
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }
}
