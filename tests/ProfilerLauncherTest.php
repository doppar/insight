<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

use Doppar\Insight\Collectors\HttpCollector;
use Doppar\Insight\Collectors\RequestCollector;
use Doppar\Insight\Collectors\ResponseCollector;
use Doppar\Insight\Collectors\TimeMemoryCollector;
use Doppar\Insight\Contracts\StorageInterface;
use Doppar\Insight\Middleware\ProfilerMiddleware;
use Doppar\Insight\Profiler;
use Doppar\Insight\ProfilerLauncher;
use Doppar\Insight\Support\ErrorHistoryRecorder;
use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Http\Exceptions\NotFoundHttpException;
use ReflectionMethod;

class ProfilerLauncherTest extends TestCase
{
    private ?Container $originalContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContainer = Container::getInstance();
        $property = new \ReflectionProperty(ProfilerLauncher::class, 'middlewareRegistered');
        $property->setValue(null, false);
    }

    protected function tearDown(): void
    {
        if ($this->originalContainer instanceof Container) {
            Container::setInstance($this->originalContainer);
        }

        parent::tearDown();
    }

    public function testRegisterErrorTrackingCapturesDispatchLevelHttpExceptions(): void
    {
        $terminatingCallback = null;

        /** @var Application&\PHPUnit\Framework\MockObject\MockObject $app */
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['terminating'])
            ->getMock();

        $app->expects($this->once())
            ->method('terminating')
            ->willReturnCallback(function (callable $callback) use (&$terminatingCallback, $app) {
                $terminatingCallback = $callback;

                return $app;
            });

        Container::setInstance($app);

        $storage = new class implements StorageInterface
        {
            /** @var array<string, array<string, mixed>> */
            public array $records = [];

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
                return array_slice(array_values(array_map(function (array $data): array {
                    return [
                        'id' => $data['id'],
                        'method' => $data['method'],
                        'route' => $data['route'],
                        'status' => $data['status'],
                        'duration_ms' => $data['duration_ms'] ?? 0,
                        'exception_class' => $data['exception_class'] ?? null,
                        'exception_message' => $data['exception_message'] ?? null,
                        'captured_at' => null,
                        'captured_at_unix' => 0,
                    ];
                }, $this->records)), 0, $limit);
            }
        };
        $profiler = new Profiler(['enabled' => true], $storage);
        $profiler->addCollector(new HttpCollector());
        $profiler->addCollector(new RequestCollector());
        $profiler->addCollector(new ResponseCollector());
        $profiler->addCollector(new TimeMemoryCollector());

        $app->instance(Profiler::class, $profiler);
        $app->instance(ErrorHistoryRecorder::class, new ErrorHistoryRecorder());

        $provider = new ProfilerLauncher($app);

        $method = new ReflectionMethod($provider, 'registerErrorTracking');
        $method->invoke($provider);

        $this->assertIsCallable($terminatingCallback);

        $request = $this->createRequest('GET', '/missing-page', ['REMOTE_ADDR' => '127.0.0.1']);

        $terminatingCallback(
            $request,
            null,
            new NotFoundHttpException('Route missing')
        );

        $recent = $storage->recent();

        $this->assertCount(1, $recent);
        $this->assertSame(404, $recent[0]['status']);
        $this->assertSame('/missing-page', $recent[0]['route']);
        $this->assertSame('GET', $recent[0]['method']);
    }

    public function testProfilerIsDisabledInProductionUnlessExplicitlyAllowed(): void
    {
        $app = new class extends Application
        {
            public function __construct()
            {
            }

            public function isProduction(): bool
            {
                return true;
            }

            public function isDevelopment(): bool
            {
                return false;
            }
        };

        Container::setInstance($app);

        $storage = new class implements StorageInterface
        {
            public function put(string $id, array $data): void
            {
            }

            public function get(string $id): ?array
            {
                return null;
            }

            public function recent(int $limit = 50): array
            {
                return [];
            }
        };

        $this->assertFalse((new Profiler(['enabled' => true], $storage))->isGloballyEnabled());
        $this->assertTrue((new Profiler([
            'enabled' => true,
            'allow_production' => true,
        ], $storage))->isGloballyEnabled());
    }

    public function testRegisterMiddlewareIsIdempotent(): void
    {
        $router = new class {
            public int $calls = 0;

            public function applyMiddleware($middleware): void
            {
                if (! $middleware instanceof ProfilerMiddleware) {
                    throw new \RuntimeException('Unexpected middleware type.');
                }

                $this->calls++;
            }
        };

        $app = new class extends Application
        {
            public function __construct()
            {
            }
        };

        $app->instance('route', $router);
        $app->instance(ProfilerMiddleware::class, new ProfilerMiddleware());

        Container::setInstance($app);

        $provider = new ProfilerLauncher($app);

        $method = new \ReflectionMethod($provider, 'registerMiddleware');
        $method->invoke($provider);
        $method->invoke($provider);

        $this->assertSame(1, $router->calls);
    }
}
