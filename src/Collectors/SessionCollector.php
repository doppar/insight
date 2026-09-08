<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class SessionCollector implements CollectorInterface
{
    use UsesSensitiveDataSanitizer;

    /** @var array<string, mixed> */
    protected array $data = [];

    public function name(): string
    {
        return 'session';
    }

    public function start(Request $request): void
    {
        $this->data = [
            'session_data' => [],
        ];

        if (isset($_SESSION)) {
            $sessionData = $_SESSION;
            unset($sessionData['password'], $sessionData['_token']);
            $sanitizer = $this->sanitizer();
            $this->data['session_data'] = $sanitizer->sanitize($sessionData);
        }
    }

    public function stop(Request $request, Response $response): void
    {
        // Nothing to do on stop for auth collector
    }

    public function toArray(): array
    {
        return [
            'session_data' => $this->data['session_data'],
        ];
    }
}
