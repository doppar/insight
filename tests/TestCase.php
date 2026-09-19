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
        $originalServer = $_SERVER;
        $originalGet = $_GET;
        $originalPost = $_POST;
        $originalCookie = $_COOKIE;
        $originalFiles = $_FILES;

        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'localhost',
        ];

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $parsedQuery);

        $_SERVER = array_merge($originalServer, $defaultServer, $server);
        $_GET = $query !== [] ? $query : ($originalGet !== [] ? $originalGet : $parsedQuery);
        $_POST = $post !== [] ? $post : $originalPost;
        $_COOKIE = $originalCookie;
        $_FILES = $originalFiles;

        try {
            // Since 4.x the constructor no longer reads the superglobals, so
            // pass them in explicitly.
            return new Request($_GET, $_POST, [], $_COOKIE, $_FILES, $_SERVER);
        } finally {
            $_SERVER = $originalServer;
            $_GET = $originalGet;
            $_POST = $originalPost;
            $_COOKIE = $originalCookie;
            $_FILES = $originalFiles;
        }
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
