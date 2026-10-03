<?php

namespace Doppar\Insight\Cache;

use Phaseolies\Cache\TaggedCache;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * A tagged cache that reports what it does to the profiler, with the tags on each entry.
 */
class ProfilerTaggedCache extends TaggedCache
{
    /**
     * @param ProfilerCacheStore $profiler
     * @param TagAwareAdapter $pool
     * @param array<int, string> $tags
     */
    public function __construct(
        protected ProfilerCacheStore $profiler,
        TagAwareAdapter $pool,
        array $tags
    ) {
        parent::__construct($profiler, $pool, $tags);
    }

    public function get($key, $default = null): mixed
    {
        $missing = new \stdClass();
        $value = parent::get($key, $missing);
        $hit = $value !== $missing;

        $this->profiler->recordTagged('get', (string) $key, $hit ? $value : $default, $hit, $this->tags);

        return $hit ? $value : $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $result = parent::set($key, $value, $ttl);

        $this->profiler->recordTagged('set', (string) $key, $value, false, $this->tags, true, $this->store->ttlSeconds($ttl));

        return $result;
    }

    public function forever($key, $value): bool
    {
        $result = parent::forever($key, $value);

        $this->profiler->recordTagged('forever', (string) $key, $value, false, $this->tags, true, null);

        return $result;
    }

    public function has($key): bool
    {
        $result = parent::has($key);

        $this->profiler->recordTagged('has', (string) $key, null, $result, $this->tags);

        return $result;
    }

    public function delete($key): bool
    {
        $result = parent::delete($key);

        $this->profiler->recordTagged('delete', (string) $key, null, false, $this->tags);

        return $result;
    }

    public function pull($key, $default = null): mixed
    {
        $missing = new \stdClass();
        $value = parent::pull($key, $missing);
        $hit = $value !== $missing;

        $this->profiler->recordTagged('get', (string) $key, $hit ? $value : $default, $hit, $this->tags);

        if ($hit) {
            $this->profiler->recordTagged('forget', (string) $key, null, false, $this->tags);
        }

        return $hit ? $value : $default;
    }

    public function flush(): bool
    {
        $result = parent::flush();

        $this->profiler->recordTagged('clear', '*', null, false, $this->tags);

        return $result;
    }

    protected function remember(string $key, ?int $seconds, \Closure $callback): mixed
    {
        $missed = false;

        $value = parent::remember($key, $seconds, function () use ($callback, &$missed) {
            $missed = true;

            return $callback();
        });

        if (! $missed) {
            $this->profiler->recordTagged('get', $key, $value, true, $this->tags);

            return $value;
        }

        $this->profiler->recordTagged('get', $key, null, false, $this->tags);
        $this->profiler->recordTagged($seconds === null ? 'forever' : 'set', $key, $value, false, $this->tags, true, $seconds);

        return $value;
    }
}
