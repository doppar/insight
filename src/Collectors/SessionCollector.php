<?php

namespace Doppar\Insight\Collectors;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Support\Facades\Auth;
use Doppar\Insight\Contracts\CollectorInterface;

class SessionCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'session';
    }

    public function start(Request $request): void
    {
        $this->data = [
            'session_data' => []
        ];

        // Collect session data if available
        if (session_status() === PHP_SESSION_ACTIVE || @session_start()) {
            // Collect relevant session data (excluding sensitive info)
            $sessionData = $_SESSION;
            unset($sessionData['password'], $sessionData['_token']);
            $this->data['session_data'] = $sessionData;
        }
    }

    public function stop(Request $request, Response $response): void
    {
        // Nothing to do on stop for auth collector
    }

    public function toArray(): array
    {
        return [
            'session_data' => $this->data['session_data']
        ];
    }
}
