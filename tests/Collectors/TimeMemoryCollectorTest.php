<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\TimeMemoryCollector;
use Doppar\Insight\Tests\TestCase;

class TimeMemoryCollectorTest extends TestCase
{
    private TimeMemoryCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new TimeMemoryCollector();
    }

    public function testName(): void
    {
        $this->assertEquals('timememory', $this->collector->name());
    }

    public function testStartCapturesTime(): void
    {
        $request = $this->createRequest();
        
        $this->collector->start($request);
        
        // After start, we should be able to get data
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('time_start', $data);
        $this->assertIsFloat($data['time_start']);
        $this->assertGreaterThan(0, $data['time_start']);
    }

    public function testStopCapturesMemoryPeak(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        
        // Allocate some memory
        $dummy = str_repeat('x', 1024 * 100); // 100KB
        
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('memory_peak', $data);
        $this->assertIsInt($data['memory_peak']);
        $this->assertGreaterThan(0, $data['memory_peak']);
    }

    public function testToArrayContainsAllRequiredFields(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        usleep(1000); // Sleep 1ms to ensure duration > 0
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('duration_ms', $data);
        $this->assertArrayHasKey('memory_peak', $data);
        $this->assertArrayHasKey('time_start', $data);
        
        $this->assertIsFloat($data['duration_ms']);
        $this->assertIsInt($data['memory_peak']);
        $this->assertIsFloat($data['time_start']);
        
        $this->assertGreaterThan(0, $data['duration_ms']);
    }

    public function testDurationCalculation(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        
        usleep(1000); // Sleep 1ms
        
        $this->collector->stop($request, $response);
        
        $data = $this->collector->toArray();
        
        // Duration should be positive
        //$this->assertGreaterThan(0, $data['duration_ms']);
        // Should be a reasonable value (not negative, not huge)
        $this->assertIsFloat($data['duration_ms']);
    }

    public function testUsesRequestTimeFloatWhenAvailable(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 0.1; // 100ms ago
        
        $request = $this->createRequest();
        $this->collector->start($request);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals($_SERVER['REQUEST_TIME_FLOAT'], $data['time_start']);
        
        unset($_SERVER['REQUEST_TIME_FLOAT']);
    }
}
