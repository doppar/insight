<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\SessionCollector;
use Doppar\Insight\Tests\TestCase;

class SessionCollectorTest extends TestCase
{
    private SessionCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new SessionCollector();
        
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Clean up session after each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        parent::tearDown();
    }

    public function testName(): void
    {
        $this->assertEquals('session', $this->collector->name());
    }

    public function testCollectsSessionData(): void
    {
        // Start session and add data
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['user_id'] = 123;
        $_SESSION['username'] = 'john_doe';
        $_SESSION['preferences'] = ['theme' => 'dark'];
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('session_data', $data);
        $this->assertEquals(123, $data['session_data']['user_id']);
        $this->assertEquals('john_doe', $data['session_data']['username']);
        $this->assertEquals(['theme' => 'dark'], $data['session_data']['preferences']);
    }

    public function testFiltersSensitivePassword(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['user_id'] = 123;
        $_SESSION['password'] = 'secret123';
        $_SESSION['username'] = 'john';
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayNotHasKey('password', $data['session_data']);
        $this->assertArrayHasKey('user_id', $data['session_data']);
        $this->assertArrayHasKey('username', $data['session_data']);
    }

    public function testFiltersSensitiveToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['user_id'] = 123;
        $_SESSION['_token'] = 'csrf_token_value';
        $_SESSION['data'] = 'safe_data';
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayNotHasKey('_token', $data['session_data']);
        $this->assertArrayHasKey('user_id', $data['session_data']);
        $this->assertArrayHasKey('data', $data['session_data']);
    }

    public function testHandlesNoActiveSession(): void
    {
        // Ensure no session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('session_data', $data);
        $this->assertIsArray($data['session_data']);
    }

    public function testStopDoesNothing(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['test'] = 'value';
        
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $dataBefore = $this->collector->toArray();
        
        $this->collector->stop($request, $response);
        $dataAfter = $this->collector->toArray();
        
        $this->assertEquals($dataBefore, $dataAfter);
    }

    public function testToArrayStructure(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['key'] = 'value';
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('session_data', $data);
        $this->assertIsArray($data['session_data']);
    }
}
