<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class RequestCollector implements CollectorInterface
{
    use UsesSensitiveDataSanitizer;

    /** @var array<string, mixed> */
    protected array $data = [];

    public function name(): string
    {
        return 'request';
    }

    public function start(Request $request): void
    {
        // Collect headers
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $sanitizer = $this->sanitizer();

        // Collect query and POST parameters
        $query = $sanitizer->sanitize($_GET);
        $post = $sanitizer->sanitize($_POST);

        // Collect request body for JSON/raw requests
        $body = null;
        $rawBody = file_get_contents('php://input');
        if (! empty($rawBody)) {
            $body = $sanitizer->sanitizeRawBody($rawBody);
        }

        // Collect uploaded files
        $files = [];
        foreach ($_FILES as $key => $file) {
            $files[$key] = [
                'name' => $file['name'] ?? null,
                'type' => $file['type'] ?? null,
                'size' => $file['size'] ?? null,
            ];
        }

        // Collect server info
        $server = [
            'METHOD' => $request->getMethod(),
            'PATH' => $request->getPath(),
            'IP' => $request->ip(),
            'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'REFERER' => $_SERVER['HTTP_REFERER'] ?? null,
        ];

        $this->data = [
            'request_headers' => $headers,
            'request_query' => $query,
            'request_params' => $post,
            'request_body' => $body,
            'request_cookies' => $sanitizer->sanitize($_COOKIE),
            'request_files' => $files,
            'request_server' => array_filter($server, fn ($v) => $v !== null),
        ];
    }

    public function stop(Request $request, Response $response): void
    {
        // Nothing to do on stop for request collector
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
