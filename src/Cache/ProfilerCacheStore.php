<?php

namespace Doppar\Insight\Cache;

use Phaseolies\Cache\CacheStore;
use Doppar\Insight\Collectors\CacheCollector;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class ProfilerCacheStore extends CacheStore
{
    public function __construct(AdapterInterface $adapter, ?string $prefix = null)
    {
        parent::__construct($adapter, $prefix);
    }

    public function get($key, $default = null): mixed
    {
        $value = parent::get($key, $default);
        $hit = $value !== $default;
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('get', $key, $value, $hit);
        }
        
        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $result = parent::set($key, $value, $ttl);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('set', $key, $value);
        }
        
        return $result;
    }

    public function delete($key): bool
    {
        $result = parent::delete($key);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('delete', $key);
        }
        
        return $result;
    }

    public function forever($key, $value): bool
    {
        $result = parent::forever($key, $value);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('forever', $key, $value);
        }
        
        return $result;
    }

    public function forget($key): bool
    {
        $result = parent::forget($key);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('forget', $key);
        }
        
        return $result;
    }

    public function has($key): bool
    {
        $result = parent::has($key);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('has', $key, null, $result);
        }
        
        return $result;
    }

    public function increment($key, $value = 1): int|bool
    {
        $result = parent::increment($key, $value);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('increment', $key, $value);
        }
        
        return $result;
    }

    public function decrement($key, $value = 1): int|bool
    {
        $result = parent::decrement($key, $value);
        
        $collector = CacheCollector::active();
        if ($collector) {
            $collector->registerOperation('decrement', $key, $value);
        }
        
        return $result;
    }
}
