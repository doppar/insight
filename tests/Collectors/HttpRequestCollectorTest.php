<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\HttpRequestCollector;
use Doppar\Insight\Tests\TestCase;

class HttpRequestCollectorTest extends TestCase
{
    private HttpRequestCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new HttpRequestCollector();
    }

    protected function tearDown(): void
    {
        // Clean up static reference
        HttpRequestCollector::setActive(null);
        parent::tearDown();
    }

    public function testName(): void
    {
        $this->assertEquals('http_requests', $this->collector->name());
    }

    public function testStartSetsActiveCollector(): void
    {
        $request = $this->createRequest();
        
        $this->collector->start($request);
        
        $this->assertSame($this->collector, HttpRequestCollector::active());
    }

    public function testStopClearsActiveCollector(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $this->assertNotNull(HttpRequestCollector::active());
        
        $this->collector->stop($request, $response);
        $this->assertNull(HttpRequestCollector::active());
    }

    public function testRegisterRequest(): void
    {
        $this->collector->registerRequest('GET', 'https://api.example.com/users', 150.5, 200, true);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('http_requests', $data);
        $this->assertCount(1, $data['http_requests']);
        
        $request = $data['http_requests'][0];
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('https://api.example.com/users', $request['url']);
        $this->assertEquals(150.5, $request['duration_ms']);
        $this->assertEquals(200, $request['status']);
        $this->assertTrue($request['successful']);
    }

    public function testRegisterMultipleRequests(): void
    {
        $this->collector->registerRequest('GET', 'https://api.example.com/users', 100, 200, true);
        $this->collector->registerRequest('POST', 'https://api.example.com/posts', 250, 201, true);
        $this->collector->registerRequest('DELETE', 'https://api.example.com/posts/1', 50, 204, true);
        
        $data = $this->collector->toArray();
        
        $this->assertCount(3, $data['http_requests']);
    }

    public function testCountsRequests(): void
    {
        $this->collector->registerRequest('GET', 'https://example.com/1', 100, 200, true);
        $this->collector->registerRequest('GET', 'https://example.com/2', 150, 200, true);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('http_requests_count', $data);
        $this->assertEquals(2, $data['http_requests_count']);
    }

    public function testCalculatesTotalTime(): void
    {
        $this->collector->registerRequest('GET', 'https://example.com/1', 100, 200, true);
        $this->collector->registerRequest('GET', 'https://example.com/2', 150.5, 200, true);
        $this->collector->registerRequest('GET', 'https://example.com/3', 50, 200, true);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('http_requests_total_time_ms', $data);
        $this->assertEquals(300.5, $data['http_requests_total_time_ms']);
    }

    public function testHandlesFailedRequests(): void
    {
        $this->collector->registerRequest('GET', 'https://example.com/fail', 1000, 500, false);
        
        $data = $this->collector->toArray();
        
        $request = $data['http_requests'][0];
        $this->assertEquals(500, $request['status']);
        $this->assertFalse($request['successful']);
    }

    public function testHandlesNullStatus(): void
    {
        $this->collector->registerRequest('GET', 'https://example.com/timeout', 5000, null, false);
        
        $data = $this->collector->toArray();
        
        $request = $data['http_requests'][0];
        $this->assertNull($request['status']);
        $this->assertFalse($request['successful']);
    }

    public function testToArrayStructure(): void
    {
        $this->collector->registerRequest('GET', 'https://example.com', 100, 200, true);
        
        $data = $this->collector->toArray();
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('http_requests', $data);
        $this->assertArrayHasKey('http_requests_count', $data);
        $this->assertArrayHasKey('http_requests_total_time_ms', $data);
        
        $this->assertIsArray($data['http_requests']);
        $this->assertIsInt($data['http_requests_count']);
        $this->assertIsFloat($data['http_requests_total_time_ms']);
    }

    public function testEmptyRequestsList(): void
    {
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('http_requests', $data);
        $this->assertEmpty($data['http_requests']);
        $this->assertEquals(0, $data['http_requests_count']);
        $this->assertEquals(0, $data['http_requests_total_time_ms']);
    }
}
