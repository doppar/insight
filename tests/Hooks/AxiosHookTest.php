<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Hooks;

use Doppar\Insight\Contracts\ProfilerHookInterface;
use Doppar\Insight\Hooks\AxiosHook;
use Doppar\Insight\Tests\TestCase;

class AxiosHookTest extends TestCase
{
    private AxiosHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new AxiosHook();
    }

    public function testImplementsProfilerHookInterface(): void
    {
        $this->assertInstanceOf(ProfilerHookInterface::class, $this->hook);
    }

    public function testRegisterInterceptsAxios(): void
    {
        $this->markTestSkipped('Requires full Application instance and Axios package');
    }

    public function testConfiguresInterceptors(): void
    {
        $this->markTestSkipped('Requires full Application instance and Axios package');
    }

    public function testHandlesAxiosNotConfigured(): void
    {
        $this->markTestSkipped('Requires full Application instance and Axios package');
    }

    public function testHandlesErrors(): void
    {
        $this->markTestSkipped('Requires full Application instance and Axios package');
    }
}
