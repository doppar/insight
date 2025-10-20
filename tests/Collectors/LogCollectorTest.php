<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\LogCollector;
use Doppar\Insight\Tests\TestCase;

class LogCollectorTest extends TestCase
{
    private LogCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new LogCollector();
    }

    protected function tearDown(): void
    {
        LogCollector::setActive(null);
        parent::tearDown();
    }

    public function testName(): void
    {
        $this->assertEquals('logs', $this->collector->name());
    }

    public function testStartSetsActiveCollector(): void
    {
        $request = $this->createRequest();
        
        $this->collector->start($request);
        
        $this->assertSame($this->collector, LogCollector::active());
    }

    public function testStopClearsActiveCollector(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse();
        
        $this->collector->start($request);
        $this->assertNotNull(LogCollector::active());
        
        $this->collector->stop($request, $response);
        $this->assertNull(LogCollector::active());
    }

    public function testRegisterLogDebugLevel(): void
    {
        $this->collector->registerLog('debug', 'Debug message', ['key' => 'value']);
        
        $data = $this->collector->toArray();
        
        $this->assertCount(1, $data['logs']);
        $this->assertEquals('debug', $data['logs'][0]['level']);
        $this->assertEquals('Debug message', $data['logs'][0]['message']);
    }

    public function testRegisterLogInfoLevel(): void
    {
        $this->collector->registerLog('info', 'Info message');
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('info', $data['logs'][0]['level']);
        $this->assertEquals('Info message', $data['logs'][0]['message']);
    }

    public function testRegisterLogWarningLevel(): void
    {
        $this->collector->registerLog('warning', 'Warning message');
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('warning', $data['logs'][0]['level']);
        $this->assertEquals('Warning message', $data['logs'][0]['message']);
    }

    public function testRegisterLogErrorLevel(): void
    {
        $this->collector->registerLog('error', 'Error message');
        
        $data = $this->collector->toArray();
        
        $this->assertEquals('error', $data['logs'][0]['level']);
        $this->assertEquals('Error message', $data['logs'][0]['message']);
    }

    public function testStoresLogContext(): void
    {
        $context = [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '192.168.1.1',
        ];
        
        $this->collector->registerLog('info', 'User logged in', $context);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals($context, $data['logs'][0]['context']);
    }

    public function testStoresTimestamp(): void
    {
        $timestamp = microtime(true);
        
        $this->collector->registerLog('info', 'Test message', [], $timestamp);
        
        $data = $this->collector->toArray();
        
        $this->assertEquals($timestamp, $data['logs'][0]['timestamp']);
    }

    public function testGeneratesTimestampWhenNotProvided(): void
    {
        $before = microtime(true);
        $this->collector->registerLog('info', 'Test message');
        $after = microtime(true);
        
        $data = $this->collector->toArray();
        
        $this->assertGreaterThanOrEqual($before, $data['logs'][0]['timestamp']);
        $this->assertLessThanOrEqual($after, $data['logs'][0]['timestamp']);
    }

    public function testFormatsTimeString(): void
    {
        $this->collector->registerLog('info', 'Test message');
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('time', $data['logs'][0]);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data['logs'][0]['time']);
    }

    public function testCountsMultipleLogs(): void
    {
        $this->collector->registerLog('debug', 'Debug 1');
        $this->collector->registerLog('info', 'Info 1');
        $this->collector->registerLog('warning', 'Warning 1');
        $this->collector->registerLog('error', 'Error 1');
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(4, $data['logs_total_count']);
        $this->assertCount(4, $data['logs']);
    }

    public function testLimitsLogListTo100(): void
    {
        // Add 150 logs
        for ($i = 0; $i < 150; $i++) {
            $this->collector->registerLog('info', "Message $i");
        }
        
        $data = $this->collector->toArray();
        
        $this->assertEquals(150, $data['logs_total_count']);
        $this->assertCount(100, $data['logs']); // Limited to 100
    }

    public function testToArrayStructure(): void
    {
        $this->collector->registerLog('info', 'Test message', ['key' => 'value']);
        
        $data = $this->collector->toArray();
        
        $this->assertArrayHasKey('logs_total_count', $data);
        $this->assertArrayHasKey('logs', $data);
        $this->assertIsInt($data['logs_total_count']);
        $this->assertIsArray($data['logs']);
        
        $log = $data['logs'][0];
        $this->assertArrayHasKey('level', $log);
        $this->assertArrayHasKey('message', $log);
        $this->assertArrayHasKey('context', $log);
        $this->assertArrayHasKey('timestamp', $log);
        $this->assertArrayHasKey('time', $log);
    }

    public function testEmptyLogsList(): void
    {
        $data = $this->collector->toArray();
        
        $this->assertEquals(0, $data['logs_total_count']);
        $this->assertEmpty($data['logs']);
    }

    public function testRegisterLogWithEmptyContext(): void
    {
        $this->collector->registerLog('info', 'Message without context');
        
        $data = $this->collector->toArray();
        
        $this->assertEmpty($data['logs'][0]['context']);
    }
}
