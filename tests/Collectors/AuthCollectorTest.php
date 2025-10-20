<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\AuthCollector;
use Doppar\Insight\Tests\TestCase;

class AuthCollectorTest extends TestCase
{
    private AuthCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new AuthCollector();
    }

    public function testName(): void
    {
        $this->assertEquals('auth', $this->collector->name());
    }

    public function testStartInitializesDefaultData(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testCollectsAuthenticatedUser(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testCollectsUserId(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testCollectsUserName(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testCollectsUserEmail(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testFiltersPasswordField(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testFiltersRememberToken(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testFiltersTwoFactorSecret(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testFiltersTwoFactorRecoveryCodes(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testHandlesUnauthenticatedUser(): void
    {
        $this->markTestSkipped('Requires Auth facade - full Application needed');
    }

    public function testHandlesAuthFacadeError(): void
    {
        $request = $this->createRequest();
        
        // Should not throw exception even if Auth facade fails
        try {
            $this->collector->start($request);
            $this->assertTrue(true); // No exception thrown
        } catch (\Exception $e) {
            $this->fail('Should handle Auth facade errors gracefully');
        }
    }

    public function testStopDoesNothing(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        // Start first to initialize data
        try {
            $this->collector->start($request);
        } catch (\Exception $e) {
            // Ignore Auth facade errors
        }
        
        $dataBefore = $this->collector->toArray();
        
        $this->collector->stop($request, $response);
        $dataAfter = $this->collector->toArray();
        
        $this->assertEquals($dataBefore, $dataAfter);
    }

    public function testToArrayStructure(): void
    {
        $request = $this->createRequest();
        
        try {
            $this->collector->start($request);
        } catch (\Exception $e) {
            // Ignore Auth facade errors
        }
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('auth_authenticated', $data);
        $this->assertArrayHasKey('auth_user_id', $data);
        $this->assertArrayHasKey('auth_user_name', $data);
        $this->assertArrayHasKey('auth_user_email', $data);
        $this->assertArrayHasKey('auth_user', $data);
        $this->assertArrayHasKey('auth_guard', $data);
    }

    public function testDefaultValuesWhenAuthNotAvailable(): void
    {
        $request = $this->createRequest();
        
        try {
            $this->collector->start($request);
        } catch (\Exception $e) {
            // Ignore Auth facade errors
        }
        
        $data = $this->collector->toArray();
        
        // When Auth is not available, should have default values
        $this->assertIsBool($data['auth_authenticated']);
        $this->assertNull($data['auth_user_id']);
        $this->assertNull($data['auth_user_name']);
        $this->assertNull($data['auth_user_email']);
        $this->assertNull($data['auth_user']);
        $this->assertNull($data['auth_guard']);
    }
}
