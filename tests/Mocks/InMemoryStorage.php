<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Mocks;

use Doppar\Insight\Contracts\StorageInterface;

/**
 * In-memory storage implementation for testing purposes
 */
class InMemoryStorage implements StorageInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $storage = [];

    public function put(string $id, array $data): void
    {
        $this->storage[$id] = $data;
    }

    public function get(string $id): ?array
    {
        return $this->storage[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->storage[$id]);
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    public function count(): int
    {
        return count($this->storage);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->storage;
    }
}
