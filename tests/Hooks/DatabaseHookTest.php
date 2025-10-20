<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Hooks\DatabaseHook;
use Doppar\Insight\Tests\TestCase;

class DatabaseHookTest extends TestCase
{
    private DatabaseHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new DatabaseHook();
    }

    public function testImplementsProfilerHookInterface(): void
    {
        $this->assertInstanceOf(ProfilerHookInterface::class, $this->hook);
    }

    public function testRegisterInterceptsPdo(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testWrapsPdoStatements(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesDatabaseNotConfigured(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesErrors(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesMultipleConnections(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }
}
