<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Middleware;

use Doppar\Insight\Middleware\ProfilerMiddleware;
use Doppar\Insight\Collectors\HttpCollector;
use Doppar\Insight\Collectors\RequestCollector;
use Doppar\Insight\Collectors\ResponseCollector;
use Doppar\Insight\Collectors\TimeMemoryCollector;
use Doppar\Insight\Profiler;
use Doppar\Insight\Tests\TestCase;
use Phaseolies\DI\Container;
use Phaseolies\Http\Response;

class ProfilerMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        Container::forgetInstance();

        parent::tearDown();
    }

    public function testHtmlInjectionDoesNotStartSessionWhenNoRedirectChainExists(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $container = new Container();
        $container->instance(Profiler::class, new class
        {
            public function isEnabledFor($request): bool
            {
                return true;
            }

            public function start($request): void
            {
            }

            public function stop($request, $response): void
            {
            }

            public function shouldInject($response): bool
            {
                return true;
            }

            public function renderToolbar(): string
            {
                return '<div id="insight-toolbar"></div>';
            }

            public function getCurrentData(): array
            {
                return ['id' => 'test-request-id'];
            }
        });
        Container::setInstance($container);

        $middleware = new ProfilerMiddleware();
        $request = $this->createRequest('GET', '/home');
        $response = $this->createResponse(200, 'text/html', '<html><body>Hello</body></html>');

        $result = $middleware($request, fn () => $response);

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertStringContainsString('<div id="insight-toolbar"></div></body>', (string) $result->body);
    }

    public function testBootstrapProbeResponseIsDiscardedAndNotStored(): void
    {
        $storage = new class implements \Doppar\Insight\Contracts\StorageInterface
        {
            /** @var array<string, array<string, mixed>> */
            private array $records = [];

            public function put(string $id, array $data): void
            {
                $this->records[$id] = $data;
            }

            public function get(string $id): ?array
            {
                return $this->records[$id] ?? null;
            }

            public function recent(int $limit = 50): array
            {
                return array_slice(array_values($this->records), 0, $limit);
            }

            public function count(): int
            {
                return count($this->records);
            }
        };
        $profiler = new Profiler(['enabled' => true], $storage);
        $profiler->addCollector(new HttpCollector());
        $profiler->addCollector(new RequestCollector());
        $profiler->addCollector(new ResponseCollector());
        $profiler->addCollector(new TimeMemoryCollector());

        $container = new Container();
        $container->instance(Profiler::class, $profiler);
        Container::setInstance($container);

        $middleware = new ProfilerMiddleware();
        $request = $this->createRequest('GET', '/users');
        $probeResponse = new Response(null, 200);

        $result = $middleware($request, fn () => $probeResponse);

        $this->assertSame($probeResponse, $result);
        $this->assertSame(0, $storage->count());
        $this->assertFalse($profiler->isRunning());
        $this->assertFalse($profiler->hasStopped());
    }
}
