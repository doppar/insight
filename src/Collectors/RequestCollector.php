<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class RequestCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'request';
    }

    public function start(Request $request): void
    {
        // Collect headers
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        // Collect query and POST parameters
        $query = $_GET ?? [];
        $post = $_POST ?? [];
        
        // Collect request body for JSON/raw requests
        $body = null;
        $rawBody = file_get_contents('php://input');
        if (!empty($rawBody)) {
            $jsonBody = json_decode($rawBody, true);
            $body = json_last_error() === JSON_ERROR_NONE ? $jsonBody : $rawBody;
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
            'request_cookies' => $_COOKIE ?? [],
            'request_files' => $files,
            'request_server' => array_filter($server, fn($v) => $v !== null),
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
