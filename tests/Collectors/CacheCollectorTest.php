<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\CacheCollector;
use Doppar\Insight\Tests\TestCase;

class CacheCollectorTest extends TestCase
{
    private CacheCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new CacheCollector();
    }

    protected function tearDown(): void
    {
        CacheCollector::setActive(null);
        parent::tearDown();
    }

    public function testName(): void
    {
        $this->assertEquals('cache', $this->collector->name());
    }

    public function testStartSetsActiveCollector(): void
    {
        $request = $this->createRequest();
        
        $this->collector->start($request);
        
        $this->assertSame($this->collector, CacheCollector::active());
    }

    public function testStopClearsActiveCollector(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $this->assertNotNull(CacheCollector::active());
        
        $this->collector->stop($request, $response);
        $this->assertNull(CacheCollector::active());
    }

    public function testRegisterGetOperationHit(): void
    {
        $this->collector->registerOperation('get', 'user:123', 'John Doe', true);
        
        $data = $this->collector->toArray();
        
        $this->assertCount(1, $data['cache_operations']);
        $this->assertEquals('get', $data['cache_operations'][0]['type']);
        $this->assertEquals('user:123', $data['cache_operations'][0]['key']);
        $this->assertTrue($data['cache_operations'][0]['hit']);
        $this->assertEquals(1, $data['cache_hits']);
        $this->assertEquals(0, $data['cache_misses']);
    }

    public function testRegisterGetOperationMiss(): void
    {
        $this->collector->registerOperation('get', 'user:456', null, false);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('get', $data['cache_operations'][0]['type']);
        $this->assertFalse($data['cache_operations'][0]['hit']);
        $this->assertEquals(0, $data['cache_hits']);
        $this->assertEquals(1, $data['cache_misses']);
    }

    public function testRegisterSetOperation(): void
    {
        $this->collector->registerOperation('set', 'config:app', 'value', false);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('set', $data['cache_operations'][0]['type']);
        $this->assertEquals(1, $data['cache_writes']);
    }

    public function testRegisterForeverOperation(): void
    {
        $this->collector->registerOperation('forever', 'permanent:key', 'value', false);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('forever', $data['cache_operations'][0]['type']);
        $this->assertEquals(1, $data['cache_writes']);
    }

    public function testRegisterDeleteOperation(): void
    {
        $this->collector->registerOperation('delete', 'old:key', null, false);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('delete', $data['cache_operations'][0]['type']);
        $this->assertEquals(1, $data['cache_deletes']);
    }

    public function testRegisterForgetOperation(): void
    {
        $this->collector->registerOperation('forget', 'temp:key', null, false);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('forget', $data['cache_operations'][0]['type']);
        $this->assertEquals(1, $data['cache_deletes']);
    }

    public function testCountsMultipleOperations(): void
    {
        $this->collector->registerOperation('get', 'key1', 'value1', true);  // hit
        $this->collector->registerOperation('get', 'key2', null, false);     // miss
        $this->collector->registerOperation('set', 'key3', 'value3', false); // write
        $this->collector->registerOperation('delete', 'key4', null, false);  // delete
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(1, $data['cache_hits']);
        $this->assertEquals(1, $data['cache_misses']);
        $this->assertEquals(1, $data['cache_writes']);
        $this->assertEquals(1, $data['cache_deletes']);
        $this->assertEquals(4, $data['cache_total']);
    }

    public function testCalculatesHitRatio(): void
    {
        $this->collector->registerOperation('get', 'key1', 'value', true);  // hit
        $this->collector->registerOperation('get', 'key2', 'value', true);  // hit
        $this->collector->registerOperation('get', 'key3', null, false);    // miss
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(2, $data['cache_hits']);
        $this->assertEquals(1, $data['cache_misses']);
        
        // Hit ratio = 2/3 = 66.67%
        $hitRatio = $data['cache_hits'] / ($data['cache_hits'] + $data['cache_misses']);
        $this->assertEqualsWithDelta(0.6667, $hitRatio, 0.001);
    }

    public function testStoresTimestamp(): void
    {
        $before = microtime(true);
        $this->collector->registerOperation('get', 'key', 'value', true);
        $after = microtime(true);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('time', $data['cache_operations'][0]);
        $this->assertGreaterThanOrEqual($before, $data['cache_operations'][0]['time']);
        $this->assertLessThanOrEqual($after, $data['cache_operations'][0]['time']);
    }

    public function testTruncatesLongStringValues(): void
    {
        $longValue = str_repeat('x', 150);
        
        $this->collector->registerOperation('get', 'key', $longValue, true);
        
        $data = $this->collector->toArray();
        
        $value = $data['cache_operations'][0]['value'];
        $this->assertStringEndsWith('...', $value);
        $this->assertLessThanOrEqual(103, strlen($value)); // 100 + '...'
    }

    public function testFormatsArrayValues(): void
    {
        $arrayValue = ['key1' => 'value1', 'key2' => 'value2'];
        
        $this->collector->registerOperation('get', 'key', $arrayValue, true);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('[array]', $data['cache_operations'][0]['value']);
    }

    public function testFormatsObjectValues(): void
    {
        $objectValue = (object)['prop' => 'value'];
        
        $this->collector->registerOperation('get', 'key', $objectValue, true);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('[object]', $data['cache_operations'][0]['value']);
    }

    public function testToArrayStructure(): void
    {
        $this->collector->registerOperation('get', 'key', 'value', true);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('cache_operations', $data);
        $this->assertArrayHasKey('cache_hits', $data);
        $this->assertArrayHasKey('cache_misses', $data);
        $this->assertArrayHasKey('cache_writes', $data);
        $this->assertArrayHasKey('cache_deletes', $data);
        $this->assertArrayHasKey('cache_total', $data);
        
        $this->assertIsArray($data['cache_operations']);
        $this->assertIsInt($data['cache_hits']);
        $this->assertIsInt($data['cache_misses']);
        $this->assertIsInt($data['cache_writes']);
        $this->assertIsInt($data['cache_deletes']);
        $this->assertIsInt($data['cache_total']);
    }

    public function testEmptyOperationsList(): void
    {
        $data = $this->collector->toArray();
        
        $this->assertEmpty($data['cache_operations']);
        $this->assertEquals(0, $data['cache_hits']);
        $this->assertEquals(0, $data['cache_misses']);
        $this->assertEquals(0, $data['cache_writes']);
        $this->assertEquals(0, $data['cache_deletes']);
        $this->assertEquals(0, $data['cache_total']);
    }
}
