<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class HttpCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'http';
    }

    public function start(Request $request): void
    {
        $this->data = [
            'method' => $request->getMethod(),
            'route' => $request->getPath(),
            'ip' => $request->ip(),
            'url' => $request->getRequestUri(),
        ];
    }

    public function stop(Request $request, Response $response): void
    {
        $this->data['status'] = $response->getStatusCode();
        $this->data['content_type'] = $response->headers->get('Content-Type') ?? '';
        
        // Detect redirects (3xx status codes)
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $this->data['is_redirect'] = true;
            $this->data['redirect_url'] = $response->headers->get('Location') ?? '';
        } else {
            $this->data['is_redirect'] = false;
        }
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
