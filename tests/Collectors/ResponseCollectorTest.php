<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\ResponseCollector;
use Doppar\Insight\Tests\TestCase;

class ResponseCollectorTest extends TestCase
{
    private ResponseCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new ResponseCollector();
    }

    public function testName(): void
    {
        $this->assertEquals('response', $this->collector->name());
    }

    public function testStartDoesNothing(): void
    {
        $request = $this->createRequest();
        
        // Start should not throw any exception
        $this->collector->start($request);
        
        // Data should be empty after start
        $data = $this->collector->toArray();
        $this->assertEmpty($data);
    }

    public function testCollectsResponseHeaders(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200, 'text/html');
        $response->headers->set('X-Custom-Header', 'custom-value');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('response_headers', $data);
        $this->assertIsArray($data['response_headers']);
        $this->assertArrayHasKey('content-type', $data['response_headers']);
        $this->assertArrayHasKey('x-custom-header', $data['response_headers']);
    }

    public function testCollectsStatusCode(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(404);
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('response_status', $data);
        $this->assertEquals(404, $data['response_status']);
    }

    public function testCollectsContentType(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200, 'application/json');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('response_content_type', $data);
        $this->assertEquals('application/json', $data['response_content_type']);
    }

    public function testDetectsRedirections(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(302);
        $response->headers->set('Location', 'https://example.com/redirect');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('is_redirect', $data);
        $this->assertTrue($data['is_redirect']);
    }

    public function testCollectsRedirectUrl(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(301);
        $response->headers->set('Location', 'https://example.com/new-location');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('redirect_url', $data);
        $this->assertEquals('https://example.com/new-location', $data['redirect_url']);
    }

    public function testCalculatesBodySize(): void
    {
        $request = $this->createRequest();
        $body = '<html><body>Test Content</body></html>';
        $response = $this->createResponse(200, 'text/html', $body);
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('response_body_size', $data);
        $this->assertEquals(strlen($body), $data['response_body_size']);
    }

    public function testToArrayContainsAllRequiredFields(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200, 'text/html', '<html></html>');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('response_headers', $data);
        $this->assertArrayHasKey('response_status', $data);
        $this->assertArrayHasKey('response_content_type', $data);
        $this->assertArrayHasKey('response_body_size', $data);
        $this->assertArrayHasKey('is_redirect', $data);
        $this->assertArrayHasKey('redirect_url', $data);
    }

    public function testNonRedirectResponseHasEmptyRedirectUrl(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200);
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertFalse($data['is_redirect']);
        $this->assertEquals('', $data['redirect_url']);
    }

    public function testHandlesEmptyBody(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(204, null, '');
        
        $this->collector->start($request);
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(0, $data['response_body_size']);
    }
}
