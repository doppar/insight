<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Collectors;

use Doppar\Insight\Collectors\SessionCollector;
use Doppar\Insight\Tests\TestCase;
use Phaseolies\Http\Request;

class SessionCollectorTest extends TestCase
{
    private SessionCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new SessionCollector();
    }

    /**
     * Build a request whose session holds the given values
     *
     * @param array<string, mixed> $values
     * @return Request
     */
    private function requestWithSession(array $values): Request
    {
        $request = $this->createRequest();
        $request->session()->put($values);

        return $request;
    }

    public function testName(): void
    {
        $this->assertEquals('session', $this->collector->name());
    }

    public function testCollectsSessionData(): void
    {
        $request = $this->requestWithSession([
            'user_id' => 123,
            'username' => 'john_doe',
            'preferences' => ['theme' => 'dark'],
        ]);

        $this->collector->start($request);

        $data = $this->collector->toArray();

        $this->assertArrayHasKey('session_data', $data);
        $this->assertEquals(123, $data['session_data']['user_id']);
        $this->assertEquals('john_doe', $data['session_data']['username']);
        $this->assertEquals(['theme' => 'dark'], $data['session_data']['preferences']);
    }

    public function testFiltersSensitivePassword(): void
    {
        $request = $this->requestWithSession([
            'user_id' => 123,
            'password' => 'secret123',
            'username' => 'john',
        ]);

        $this->collector->start($request);

        $data = $this->collector->toArray();

        $this->assertArrayNotHasKey('password', $data['session_data']);
        $this->assertArrayHasKey('user_id', $data['session_data']);
        $this->assertArrayHasKey('username', $data['session_data']);
    }

    public function testFiltersSensitiveToken(): void
    {
        $request = $this->requestWithSession([
            'user_id' => 123,
            '_token' => 'csrf_token_value',
            'data' => 'safe_data',
        ]);

        $this->collector->start($request);

        $data = $this->collector->toArray();

        $this->assertArrayNotHasKey('_token', $data['session_data']);
        $this->assertArrayHasKey('user_id', $data['session_data']);
        $this->assertArrayHasKey('data', $data['session_data']);
    }

    public function testHandlesEmptySession(): void
    {
        $this->collector->start($this->createRequest());

        $data = $this->collector->toArray();

        $this->assertArrayHasKey('session_data', $data);
        $this->assertSame([], $data['session_data']);
    }

    public function testStopDoesNothing(): void
    {
        $request = $this->requestWithSession(['test' => 'value']);
        $response = $this->createResponse();

        $this->collector->start($request);
        $dataBefore = $this->collector->toArray();

        $this->collector->stop($request, $response);
        $dataAfter = $this->collector->toArray();

        $this->assertEquals($dataBefore, $dataAfter);
    }

    public function testToArrayStructure(): void
    {
        $this->collector->start($this->requestWithSession(['key' => 'value']));

        $data = $this->collector->toArray();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('session_data', $data);
        $this->assertIsArray($data['session_data']);
    }
}
