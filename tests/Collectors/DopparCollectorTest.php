<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\DopparCollector;
use Doppar\Insight\Tests\TestCase;
use Phaseolies\Application;

class DopparCollectorTest extends TestCase
{
    private DopparCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new DopparCollector();
    }

    public function testName(): void
    {
        $this->assertEquals('doppar', $this->collector->name());
    }

    public function testStartCollectsFrameworkInfo(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testCollectsPhpVersion(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testCollectsFrameworkVersion(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testCollectsEnvironment(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testStopDoesNothing(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }

    public function testToArrayReturnsCollectedData(): void
    {
        $this->markTestSkipped('Requires full Application instance');
    }
}
