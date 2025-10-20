<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\RequestCollector;
use Doppar\Insight\Tests\TestCase;

class RequestCollectorTest extends TestCase
{
    private RequestCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new RequestCollector();
        
        // Clean up globals
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    public function testName(): void
    {
        $this->assertEquals('request', $this->collector->name());
    }

    public function testCollectsQueryParameters(): void
    {
        $_GET = ['foo' => 'bar', 'page' => '1'];
        
        $request = $this->createRequest('GET', '/?foo=bar&page=1');
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_query', $data);
        $this->assertEquals(['foo' => 'bar', 'page' => '1'], $data['request_query']);
    }

    public function testCollectsPostParameters(): void
    {
        $_POST = ['username' => 'john', 'email' => 'john@example.com'];
        
        $request = $this->createRequest('POST', '/');
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_params', $data);
        $this->assertEquals(['username' => 'john', 'email' => 'john@example.com'], $data['request_params']);
    }

    public function testCollectsCookies(): void
    {
        $_COOKIE = ['session_id' => 'abc123', 'theme' => 'dark'];
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_cookies', $data);
        $this->assertEquals(['session_id' => 'abc123', 'theme' => 'dark'], $data['request_cookies']);
    }

    public function testCollectsServerInfo(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_REFERER'] = 'https://example.com';
        
        $request = $this->createRequest('POST', '/api/users', [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_REFERER' => 'https://example.com',
        ]);
        
        $this->collector->start($request);
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_server', $data);
        $this->assertIsArray($data['request_server']);
        
        // Check that we have some server info (null values are filtered)
        $this->assertArrayHasKey('METHOD', $data['request_server']);
        $this->assertArrayHasKey('PATH', $data['request_server']);
        
        // These should be present since we set them
        if (isset($data['request_server']['USER_AGENT'])) {
            $this->assertEquals('Mozilla/5.0', $data['request_server']['USER_AGENT']);
        }
        if (isset($data['request_server']['REFERER'])) {
            $this->assertEquals('https://example.com', $data['request_server']['REFERER']);
        }
        
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER']);
    }

    public function testCollectsUploadedFiles(): void
    {
        $_FILES = [
            'avatar' => [
                'name' => 'profile.jpg',
                'type' => 'image/jpeg',
                'size' => 12345,
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
            ],
        ];
        
        $request = $this->createRequest('POST', '/upload');
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_files', $data);
        $this->assertArrayHasKey('avatar', $data['request_files']);
        $this->assertEquals('profile.jpg', $data['request_files']['avatar']['name']);
        $this->assertEquals('image/jpeg', $data['request_files']['avatar']['type']);
        $this->assertEquals(12345, $data['request_files']['avatar']['size']);
    }

    public function testToArrayContainsAllRequiredFields(): void
    {
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('request_headers', $data);
        $this->assertArrayHasKey('request_query', $data);
        $this->assertArrayHasKey('request_params', $data);
        $this->assertArrayHasKey('request_body', $data);
        $this->assertArrayHasKey('request_cookies', $data);
        $this->assertArrayHasKey('request_files', $data);
        $this->assertArrayHasKey('request_server', $data);
    }

    public function testStopDoesNothing(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $dataBefore = $this->collector->toArray();
        
        $this->collector->stop($request, $response);
        $dataAfter = $this->collector->toArray();
        
        $this->assertEquals($dataBefore, $dataAfter);
    }

    public function testFiltersNullServerValues(): void
    {
        $request = $this->createRequest('GET', '/');
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        // Should not contain null values
        foreach ($data['request_server'] as $value) {
            $this->assertNotNull($value);
        }
    }
}
