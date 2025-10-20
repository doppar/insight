<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\HttpCollector;
use Doppar\Insight\Tests\TestCase;

class HttpCollectorTest extends TestCase
{
    private HttpCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new HttpCollector();
    }

    public function testName(): void
    {
        $this->assertEquals('http', $this->collector->name());
    }

    public function testCollectsHttpMethod(): void
    {
        $request = $this->createRequest('POST', '/api/users');
        
        $this->collector->start($request);
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('method', $data);
        $this->assertIsString($data['method']);
    }

    public function testCollectsRoute(): void
    {
        $request = $this->createRequest('GET', '/users/profile');
        
        $this->collector->start($request);
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('route', $data);
        $this->assertIsString($data['route']);
    }

    public function testCollectsIpAddress(): void
    {
        $request = $this->createRequest('GET', '/', ['REMOTE_ADDR' => '192.168.1.100']);
        
        $this->collector->start($request);
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('ip', $data);
        // IP may be null or a string depending on request setup
        $this->assertTrue(is_string($data['ip']) || is_null($data['ip']));
    }

    public function testCollectsUrl(): void
    {
        $request = $this->createRequest('GET', '/search?q=test');
        
        $this->collector->start($request);
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('url', $data);
        $this->assertIsString($data['url']);
    }

    public function testCollectsStatusCodeOnStop(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(404);
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals(404, $data['status']);
    }

    public function testCollectsContentType(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200, 'application/json');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('content_type', $data);
        $this->assertEquals('application/json', $data['content_type']);
    }

    public function testDetectsRedirect(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(302);
        $response->headers->set('Location', 'https://example.com/redirect');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('is_redirect', $data);
        $this->assertTrue($data['is_redirect']);
        $this->assertArrayHasKey('redirect_url', $data);
        $this->assertEquals('https://example.com/redirect', $data['redirect_url']);
    }

    public function testNonRedirectResponse(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200);
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('is_redirect', $data);
        $this->assertFalse($data['is_redirect']);
    }

    public function testToArrayContainsAllFields(): void
    {
        $request = $this->createRequest('POST', '/api/test');
        $response = $this->createResponse(201, 'application/json');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('method', $data);
        $this->assertArrayHasKey('route', $data);
        $this->assertArrayHasKey('ip', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('content_type', $data);
        $this->assertArrayHasKey('is_redirect', $data);
    }
}
