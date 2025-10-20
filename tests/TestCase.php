<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a real Request instance for testing
     */
    protected function createRequest(
        string $method = 'GET',
        string $uri = '/',
        array $server = [],
        array $query = [],
        array $post = []
    ): Request {
        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'localhost',
        ];

        // Merge server params: custom values override defaults
        $serverParams = array_merge($defaultServer, $server);
        
        return new Request(
            $query,
            $post,
            [],
            [],
            [],
            $serverParams
        );
    }

    /**
     * Create a real Response instance for testing
     */
    protected function createResponse(
        int $statusCode = 200,
        ?string $contentType = null,
        string $body = ''
    ): Response {
        $response = new Response($body, $statusCode);
        
        if ($contentType !== null) {
            $response->headers->set('Content-Type', $contentType);
        }

        return $response;
    }
}
