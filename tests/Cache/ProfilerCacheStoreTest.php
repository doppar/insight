<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Cache;

use Doppar\Insight\Cache\ProfilerCacheStore;
use Doppar\Insight\Collectors\CacheCollector;
use Doppar\Insight\Tests\TestCase;
use Phaseolies\Cache\CacheStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ProfilerCacheStoreTest extends TestCase
{
    private CacheCollector $collector;

    private ProfilerCacheStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = new CacheCollector();
        $this->collector->start($this->createRequest());
        $this->store = new ProfilerCacheStore(new ArrayAdapter(), 'test_', 'array', 'array');
    }

    protected function tearDown(): void
    {
        CacheCollector::setActive(null);
        parent::tearDown();
    }

    public function testSetCapturesStoreAndTtlMetadata(): void
    {
        $this->store->set('profile', ['name' => 'Taylor'], 120);

        $data = $this->collector->toArray();
        $operation = $data['cache_operations'][0];

        $this->assertSame('set', $operation['type']);
        $this->assertSame('array', $operation['store_name']);
        $this->assertSame('array', $operation['store_driver']);
        $this->assertSame(120, $operation['ttl_seconds']);
        $this->assertNotNull($operation['expires_at']);
    }

    public function testLockLifecycleIsCaptured(): void
    {
        $lock = $this->store->locked('insight_sync', 5, 'owner-1');

        $this->assertTrue($lock->get());
        $this->assertTrue($lock->release());

        $data = $this->collector->toArray();
        $types = array_column($data['cache_operations'], 'type');

        $this->assertContains('lock_prepare', $types);
        $this->assertContains('lock_get', $types);
        $this->assertContains('lock_release', $types);
        $this->assertGreaterThanOrEqual(3, $data['cache_lock_operations']);
    }

    public function testGetMultipleTracksHitsWithoutComparingAgainstDefaultValue(): void
    {
        $this->store->set('same_as_default', 'fallback', 60);
        $this->store->set('other', 'actual', 60);

        $values = $this->store->getMultiple(['same_as_default', 'other', 'missing'], 'fallback');

        $this->assertSame([
            'same_as_default' => 'fallback',
            'other' => 'actual',
            'missing' => 'fallback',
        ], $values);

        $data = $this->collector->toArray();
        $multiReads = array_values(array_filter($data['cache_operations'], fn (array $operation): bool => $operation['type'] === 'get_multiple'));

        $this->assertCount(3, $multiReads);
        $this->assertTrue($multiReads[0]['hit']);
        $this->assertTrue($multiReads[1]['hit']);
        $this->assertFalse($multiReads[2]['hit']);
        $this->assertSame(2, $data['cache_hits']);
        $this->assertSame(1, $data['cache_misses']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operations(): array
    {
        return $this->collector->toArray()['cache_operations'];
    }

    private function requireTaggedCaches(): void
    {
        if (! method_exists(CacheStore::class, 'tags')) {
            $this->markTestSkipped('Needs a framework with Cache::tags(), pull() and store().');
        }
    }

    public function testStashForeverIsRecordedAsAMissWithAWriteThenAHit(): void
    {
        $this->store->stashForever('countries', fn () => ['bd']);
        $this->store->stashForever('countries', fn () => ['never used']);

        $data = $this->collector->toArray();
        $types = array_column($data['cache_operations'], 'type');

        $this->assertContains('forever', $types);
        $this->assertSame(1, $data['cache_writes'], 'the second call is served from the cache');
        $this->assertSame(1, $data['cache_hits']);
        $this->assertSame('countries', $data['cache_operations'][0]['key']);
    }

    public function testStashIsRecordedWithItsTtl(): void
    {
        $this->store->stash('report', 90, fn () => 'built');

        $writes = array_values(array_filter($this->operations(), fn (array $op): bool => $op['type'] === 'set'));

        $this->assertCount(1, $writes);
        $this->assertSame('report', $writes[0]['key']);
        $this->assertSame(90, $writes[0]['ttl_seconds']);
    }

    public function testStashReturnsTheValueAndRunsTheCallbackOnce(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            return 'v' . ++$calls;
        };

        $this->assertSame('v1', $this->store->stash('k', 60, $compute));
        $this->assertSame('v1', $this->store->stash('k', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function testPullIsRecordedAsAReadAndADelete(): void
    {
        $this->requireTaggedCaches();

        $this->store->set('token', 'abc', 60);

        $this->assertSame('abc', $this->store->pull('token'));
        $this->assertSame('fallback', $this->store->pull('token', 'fallback'));

        $data = $this->collector->toArray();

        $this->assertSame(1, $data['cache_hits']);
        $this->assertSame(1, $data['cache_misses']);
        $this->assertSame(1, $data['cache_deletes']);
    }

    public function testTaggedOperationsAreRecordedWithTheirTags(): void
    {
        $this->requireTaggedCaches();

        $tagged = $this->store->tags(['users', 'reports']);
        $tagged->set('user.1', 'ada', 60);
        $this->assertSame('ada', $tagged->get('user.1'));
        $tagged->stash('top', 30, fn () => 'x');
        $tagged->flush();

        $operations = $this->operations();
        $types = array_column($operations, 'type');

        $this->assertSame(['set', 'get', 'get', 'set', 'clear'], $types);

        foreach ($operations as $operation) {
            $this->assertSame(['users', 'reports'], $operation['tags']);
        }

        $this->assertSame(60, $operations[0]['ttl_seconds']);
        $this->assertSame('user.1', $operations[1]['key']);
        $this->assertTrue($operations[1]['hit']);
        $this->assertFalse($operations[2]['hit'], 'the stash missed');
    }

    public function testTaggedCacheStillWorks(): void
    {
        $this->requireTaggedCaches();

        $this->store->tags('a')->set('k1', 1);
        $this->store->tags('b')->set('k2', 2);
        $this->store->tags('a')->flush();

        $this->assertFalse($this->store->tags('a')->has('k1'));
        $this->assertSame(2, $this->store->tags('b')->get('k2'));
    }

    public function testOtherStoresAreProfiledToo(): void
    {
        $this->requireTaggedCaches();

        $other = new CacheStore(new ArrayAdapter(), 'other_');
        $this->store->resolveStoresUsing('array', fn () => $other);

        $this->assertSame($this->store, $this->store->store(), 'no name means this store');

        $profiled = $this->store->store('redis');
        $this->assertInstanceOf(ProfilerCacheStore::class, $profiled);
        $this->assertSame($profiled, $this->store->store('redis'), 'built once');

        $profiled->set('k', 'v', 30);

        $operation = $this->operations()[0];
        $this->assertSame('set', $operation['type']);
        $this->assertSame('redis', $operation['store_name']);
    }
}
