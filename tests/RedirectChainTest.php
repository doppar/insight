<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

use Doppar\Insight\Contracts\StorageInterface;
use Doppar\Insight\Middleware\ProfilerMiddleware;
use Doppar\Insight\Profiler;
use Phaseolies\DI\Container;

class RedirectChainTest extends TestCase
{
    private function storage(array $records = []): StorageInterface
    {
        return new class($records) implements StorageInterface
        {
            public function __construct(private array $records)
            {
            }

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
        };
    }

    public function testChainIsResolvedFromStorageUsingTheIdsKeptInTheSession(): void
    {
        $profiler = new Profiler([], $this->storage([
            'aaa' => ['id' => 'aaa', 'sql' => [['query' => 'select 1']], 'redirect_chain' => [['id' => 'older']]],
        ]));

        $request = $this->createRequest();
        $request->session()->put('_insight_redirect_chain', ['aaa']);

        $chain = $profiler->getRedirectChain(false, $request);

        $this->assertCount(1, $chain);
        $this->assertSame('aaa', $chain[0]['id']);
        $this->assertSame('select 1', $chain[0]['sql'][0]['query']);
        $this->assertArrayNotHasKey('redirect_chain', $chain[0], 'A hop must not carry the chain nested inside it.');
    }

    public function testUnknownIdsAreSkippedAndClearingForgetsTheChain(): void
    {
        $profiler = new Profiler([], $this->storage(['aaa' => ['id' => 'aaa']]));

        $request = $this->createRequest();
        $request->session()->put('_insight_redirect_chain', ['gone', 'aaa']);

        $this->assertSame(['aaa'], array_column($profiler->getRedirectChain(true, $request), 'id'));
        $this->assertFalse($request->session()->has('_insight_redirect_chain'));
    }

    public function testEntriesFromOlderVersionsThatStoredWholeProfilesStillWork(): void
    {
        $profiler = new Profiler([], $this->storage());

        $request = $this->createRequest();
        $request->session()->put('_insight_redirect_chain', [['id' => 'legacy']]);

        $this->assertSame(['legacy'], array_column($profiler->getRedirectChain(false, $request), 'id'));
    }

    public function testTheMiddlewareKeepsOnlyTheRequestIdOfARedirectInTheSession(): void
    {
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
                return false;
            }

            public function getCurrentData(): array
            {
                return ['id' => 'req-1', 'sql' => array_fill(0, 200, ['query' => str_repeat('x', 100)])];
            }
        });
        Container::setInstance($container);

        $request = $this->createRequest('POST', '/profile');
        $response = $this->createResponse(302, 'text/html', '');

        (new ProfilerMiddleware())($request, fn () => $response);

        $stored = $request->session()->get('_insight_redirect_chain');

        $this->assertSame(['req-1'], $stored);
        $this->assertLessThan(50, strlen((string) json_encode($stored)));

        Container::forgetInstance();
    }
}
