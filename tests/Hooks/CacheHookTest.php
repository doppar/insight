<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Hooks\CacheHook;
use Doppar\Insight\Tests\TestCase;

class CacheHookTest extends TestCase
{
    private CacheHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new CacheHook();
    }

    public function testImplementsProfilerHookInterface(): void
    {
        $this->assertInstanceOf(ProfilerHookInterface::class, $this->hook);
    }

    public function testRegisterReplacesCache(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesCacheNotConfigured(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesErrors(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testRegistersSingleton(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }
}
