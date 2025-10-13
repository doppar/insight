<?php

namespace Doppar\Insight\Contracts;

interface StorageInterface
{
    /**
     * Persist a request profile by id
     * @param string $id
     * @param array<string,mixed> $data
     */
    public function put(string $id, array $data): void;

    /**
     * Retrieve a stored profile or null
     * @return array<string,mixed>|null
     */
    public function get(string $id): ?array;
}
