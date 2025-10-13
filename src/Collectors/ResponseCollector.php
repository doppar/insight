<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class ResponseCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'response';
    }

    public function start(Request $request): void
    {
        // Nothing to collect at start for response
    }

    public function stop(Request $request, Response $response): void
    {
        // Collect response headers
        $headers = [];
        if (method_exists($response, 'headers') && $response->headers) {
            $headers = method_exists($response->headers, 'all') ? $response->headers->all() : [];
        } else {
            // Fallback to headers_list()
            foreach (headers_list() as $header) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $headers[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        
        // Collect response info
        $statusCode = $response->getStatusCode();
        $contentType = $response->headers->get('Content-Type') ?? '';
        
        // Detect redirects
        $isRedirect = $statusCode >= 300 && $statusCode < 400;
        $redirectUrl = $isRedirect ? ($response->headers->get('Location') ?? '') : '';
        
        // Collect response body info (without the actual content for performance)
        $bodySize = 0;
        if (method_exists($response, 'getContent')) {
            $bodySize = strlen($response->getContent() ?? '');
        } elseif (isset($response->body)) {
            $bodySize = strlen($response->body ?? '');
        }
        
        $this->data = [
            'response_headers' => $headers,
            'response_status' => $statusCode,
            'response_content_type' => $contentType,
            'response_body_size' => $bodySize,
            'is_redirect' => $isRedirect,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
