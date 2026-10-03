<?php

namespace Doppar\Insight\Cache;

use Doppar\Insight\Collectors\CacheCollector;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Cache\Lock\AtomicLock;
use Phaseolies\Cache\TaggedCache;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class ProfilerCacheStore extends CacheStore
{
    protected string $storeName;

    protected ?string $storeDriver;

    /**
     * Other stores, wrapped so that what happens in them is profiled too.
     *
     * @var array<string, self>
     */
    protected array $profiledStores = [];

    public function __construct(
        AdapterInterface $adapter,
        ?string $prefix = null,
        ?string $storeName = null,
        ?string $storeDriver = null
    ) {
        parent::__construct($adapter, $prefix);
        $this->storeName = $storeName ?: (string) config('caching.default', 'default');
        $this->storeDriver = $storeDriver ?: $this->resolveStoreDriver($this->storeName);
    }

    public function get($key, $default = null): mixed
    {
        $prefixedKey = $this->prefixedValidatedKey($key);
        $item = $this->adapter->getItem($prefixedKey);
        $value = $item->isHit() ? $item->get() : $default;

        $this->recordOperation('get', (string) $key, $value, $item->isHit(), $this->itemMetadata($item));

        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $result = parent::set($key, $value, $ttl);
        $this->recordOperation('set', (string) $key, $value, false, $this->ttlMetadata($ttl));

        return $result;
    }

    public function delete($key): bool
    {
        $result = parent::delete($key);
        $this->recordOperation('delete', (string) $key, null, false);

        return $result;
    }

    public function clear(): bool
    {
        $result = parent::clear();
        $this->recordOperation('clear', '*', null, false);

        return $result;
    }

    /**
     * @param iterable<int, string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $keys = $this->normalizeKeyList($keys);
        $prefixedKeys = [];

        foreach ($keys as $key) {
            $prefixedKeys[$key] = $this->prefixedValidatedKey($key);
        }

        $items = $this->adapter->getItems(array_values($prefixedKeys));
        $results = [];
        $seen = [];

        foreach ($items as $prefixedKey => $item) {
            $originalKey = substr((string) $prefixedKey, strlen($this->prefix));
            $hit = $item->isHit();
            $value = $hit ? $item->get() : $default;

            $results[$originalKey] = $value;
            $seen[$originalKey] = true;
            $this->recordOperation('get_multiple', $originalKey, $value, $hit, $this->itemMetadata($item));
        }

        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                continue;
            }

            $results[$key] = $default;
            $this->recordOperation('get_multiple', $key, $default, false);
        }

        return $results;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $result = parent::setMultiple($values, $ttl);

        foreach ($this->iterableToArray($values, true) as $key => $value) {
            $this->recordOperation('set_multiple', (string) $key, $value, false, $this->ttlMetadata($ttl));
        }

        return $result;
    }

    /**
     * @param iterable<int, string> $keys
     */
    public function deleteMultiple($keys): bool
    {
        $result = parent::deleteMultiple($keys);

        foreach ($this->iterableToArray($keys, false) as $key) {
            $this->recordOperation('delete_multiple', (string) $key, null, false);
        }

        return $result;
    }

    public function forever($key, $value): bool
    {
        $result = parent::forever($key, $value);
        $this->recordOperation('forever', (string) $key, $value, false, $this->ttlMetadata(null));

        return $result;
    }

    public function forget($key): bool
    {
        $result = parent::forget($key);
        $this->recordOperation('forget', (string) $key, null, false);

        return $result;
    }

    public function has($key): bool
    {
        $result = parent::has($key);
        $this->recordOperation('has', (string) $key, null, $result);

        return $result;
    }

    public function increment($key, $value = 1): int|bool
    {
        $result = parent::increment($key, $value);

        $this->recordOperation(
            'increment',
            (string) $key,
            $result,
            false,
            $this->itemMetadataForKey((string) $key, ['delta' => $value])
        );

        return $result;
    }

    public function decrement($key, $value = 1): int|bool
    {
        $result = parent::decrement($key, $value);

        $this->recordOperation(
            'decrement',
            (string) $key,
            $result,
            false,
            $this->itemMetadataForKey((string) $key, ['delta' => $value])
        );

        return $result;
    }

    public function add($key, $value, $ttl = null): bool
    {
        $result = parent::add($key, $value, $ttl);
        $this->recordOperation('add', (string) $key, $value, $result, $this->ttlMetadata($ttl));

        return $result;
    }

    /**
     * stash(), stashForever() and stashWhen() read and write through the adapter in one
     * step, so none of the methods above run. Record what happened as the reads and
     * writes it amounts to: a read that hit, or a read that missed followed by a write.
     */
    protected function remember(string $key, ?int $seconds, \Closure $callback): mixed
    {
        $missed = false;

        $value = parent::remember($key, $seconds, function () use ($callback, &$missed) {
            $missed = true;

            return $callback();
        });

        if (! $missed) {
            $this->recordOperation('get', $key, $value, true, $this->itemMetadataForKey($key));

            return $value;
        }

        $this->recordOperation('get', $key, null, false);
        $this->recordOperation($seconds === null ? 'forever' : 'set', $key, $value, false, $this->ttlMetadata($seconds));

        return $value;
    }

    public function pull($key, $default = null): mixed
    {
        $missing = new \stdClass();
        $value = parent::pull($key, $missing);
        $hit = $value !== $missing;

        $this->recordOperation('get', (string) $key, $hit ? $value : $default, $hit);

        if ($hit) {
            $this->recordOperation('forget', (string) $key, null, false);
        }

        return $hit ? $value : $default;
    }

    /**
     * A tagged cache whose reads, writes and flushes are recorded with their tags.
     *
     * @param string|array<int, string> $names
     */
    public function tags(string|array $names): TaggedCache
    {
        // Throws when the adapter cannot keep tagged items.
        $tagged = parent::tags($names);
        $pool = $this->taggedPool();

        return $pool === null ? $tagged : new ProfilerTaggedCache($this, $pool, $tagged->getTags());
    }

    /**
     * Another store from the cache config, profiled like this one.
     */
    public function store(?string $name = null): CacheStore
    {
        $store = parent::store($name);

        if ($store === $this || $store instanceof self) {
            return $store;
        }

        return $this->profiledStores[(string) $name] ??= (new self($store->getAdapter(), $store->getPrefix(), $name))
            ->inheritSettingsFrom($store);
    }

    /**
     * Record an operation of a tagged cache.
     *
     * @param array<int, string> $tags
     */
    public function recordTagged(
        string $type,
        string $key,
        mixed $value,
        bool $hit,
        array $tags,
        bool $withTtl = false,
        ?int $ttlSeconds = null
    ): void {
        $meta = $withTtl ? $this->ttlMetadata($ttlSeconds) : [];

        $this->recordOperation($type, $key, $value, $hit, array_merge($meta, ['tags' => $tags]));
    }

    public function locked(string $name, int $seconds = 10, ?string $owner = null): AtomicLock
    {
        $this->recordOperation('lock_prepare', $name, null, false, [
            'lock_action' => 'prepare',
            'lock_seconds' => $seconds,
            'lock_owner' => $owner,
        ]);

        return new ProfilerAtomicLock($this, $name, $seconds, $owner);
    }

    public function restoreLock(string $name, string $owner): AtomicLock
    {
        $lock = parent::restoreLock($name, $owner);
        $this->recordOperation('lock_restore', $name, null, $lock->isRestored(), [
            'lock_action' => 'restore',
            'lock_owner' => $owner,
            'lock_seconds' => $lock->getSeconds(),
            'lock_restored' => $lock->isRestored(),
        ]);

        return ProfilerAtomicLock::fromExisting($this, $lock);
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function recordOperation(
        string $type,
        string $key,
        mixed $value = null,
        bool $hit = false,
        array $meta = []
    ): void {
        $collector = CacheCollector::active();
        if (! $collector) {
            return;
        }

        $collector->registerOperation($type, $key, $value, $hit, $this->baseMetadata($meta));
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    protected function baseMetadata(array $meta = []): array
    {
        $defaults = [
            'store_name' => $this->storeName,
            'store_driver' => $this->storeDriver,
            'tags' => [],
        ];

        return array_merge($defaults, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ttlMetadata(mixed $ttl): array
    {
        $seconds = $this->convertTtlToSeconds($ttl);
        if ($seconds === null) {
            return [
                'ttl_seconds' => null,
                'expires_at_unix' => null,
                'expires_at' => null,
            ];
        }

        $expiresAt = time() + max(0, $seconds);

        return [
            'ttl_seconds' => $seconds,
            'expires_at_unix' => $expiresAt,
            'expires_at' => date(DATE_ATOM, $expiresAt),
        ];
    }

    /**
     * @param object $item
     * @return array<string, mixed>
     */
    protected function itemMetadata(object $item): array
    {
        $expiry = null;
        if (method_exists($item, 'getMetadata')) {
            $metadata = $item->getMetadata();
            $rawExpiry = $metadata['expiry'] ?? null;
            if (is_numeric($rawExpiry)) {
                $expiry = (int) $rawExpiry;
            }
        }

        if ($expiry === null) {
            return [
                'ttl_seconds' => null,
                'expires_at_unix' => null,
                'expires_at' => null,
            ];
        }

        return [
            'ttl_seconds' => max(0, $expiry - time()),
            'expires_at_unix' => $expiry,
            'expires_at' => date(DATE_ATOM, $expiry),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    protected function itemMetadataForKey(string $key, array $meta = []): array
    {
        $prefixedKey = $this->prefixedValidatedKey($key);
        $item = $this->adapter->getItem($prefixedKey);

        return array_merge($this->itemMetadata($item), $meta);
    }

    protected function resolveStoreDriver(string $storeName): ?string
    {
        // The driver is only a label in the toolbar, so an unavailable config must not break caching.
        try {
            $driver = config("caching.stores.{$storeName}.driver");
        } catch (\Throwable) {
            return null;
        }

        return is_string($driver) ? $driver : null;
    }

    public function getStoreName(): string
    {
        return $this->storeName;
    }

    public function getStoreDriver(): ?string
    {
        return $this->storeDriver;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function iterableToArray(mixed $values, bool $preserveKeys): array
    {
        if ($values instanceof \Traversable) {
            return iterator_to_array($values, $preserveKeys);
        }

        return is_array($values) ? $values : [];
    }
}
