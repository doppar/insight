<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Hooks\LoggerHook;
use Doppar\Insight\Tests\TestCase;

class LoggerHookTest extends TestCase
{
    private LoggerHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new LoggerHook();
    }

    public function testImplementsProfilerHookInterface(): void
    {
        $this->assertInstanceOf(ProfilerHookInterface::class, $this->hook);
    }

    public function testRegisterWrapsLogger(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testUsesProfilerLoggerWrapper(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesLoggerNotConfigured(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testHandlesErrors(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testPersistsHandlerAcrossResets(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }
}
