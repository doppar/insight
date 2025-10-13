<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Application;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class DopparCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'doppar';
    }

    public function start(Request $request): void
    {
        $this->data = [
            'framework_version' => $this->getFrameworkVersion(),
            'php_version' => PHP_VERSION,
            'environment' => app()->getEnvironment(),
        ];
    }

    public function stop(Request $request, Response $response): void
    {
        // Nothing to collect on stop
    }

    public function toArray(): array
    {
        return $this->data;
    }

    protected function getFrameworkVersion(): string
    {
        if (defined('Phaseolies\Application::VERSION')) {
            return Application::VERSION;
        }

        return 'unknown';
    }
}
