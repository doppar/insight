<?php

namespace Doppar\Insight\Collectors;

use Doppar\Insight\Contracts\CollectorInterface;
use Doppar\Insight\Support\UsesSensitiveDataSanitizer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class ResponseCollector implements CollectorInterface
{
    use UsesSensitiveDataSanitizer;

    /** @var array<string, mixed> */
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
        $headers = $response->headers->all();

        // Collect response info
        $statusCode = $response->getStatusCode();
        $statusText = $response->getStatusCodeText($statusCode);
        $contentType = $this->resolveContentType($request, $response);
        $headerHighlights = $this->extractHeaderHighlights($response);
        $responseClass = $this->classifyResponse($response, $contentType);

        // Detect redirects
        $isRedirect = $response->isRedirection();
        $redirectUrl = $isRedirect ? ($response->headers->get('Location') ?? '') : '';

        // Collect response body info (without the actual content for performance)
        $bodySize = 0;
        if (isset($response->body)) {
            $bodySize = strlen($response->body ?? '');
        }

        [$preview, $previewFormat, $previewTruncated] = $this->buildBodyPreview($response, $contentType);

        $this->data = [
            'response_headers' => $headers,
            'response_status' => $statusCode,
            'response_status_text' => $statusText,
            'response_content_type' => $contentType,
            'response_body_size' => $bodySize,
            'response_header_count' => count($headers),
            'response_header_highlights' => $headerHighlights,
            'response_classification' => $responseClass,
            'response_preview' => $preview,
            'response_preview_format' => $previewFormat,
            'response_preview_truncated' => $previewTruncated,
            'is_redirect' => $isRedirect,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }

    protected function resolveContentType(Request $request, Response $response): string
    {
        $contentType = $response->headers->get('Content-Type') ?? '';
        if ($contentType !== '') {
            return $contentType;
        }

        if ($response->isInformational() || $response->isEmpty()) {
            return '';
        }

        $format = $request->getRequestFormat(null);
        if ($format !== null) {
            $mimeType = $request->getMimeType($format);
            if (is_string($mimeType) && $mimeType !== '') {
                if (stripos($mimeType, 'text/') === 0 && stripos($mimeType, 'charset') === false) {
                    return $mimeType . '; charset=UTF-8';
                }

                return $mimeType;
            }
        }

        return 'text/html; charset=UTF-8';
    }

    /**
     * @return array<string, string>
     */
    protected function extractHeaderHighlights(Response $response): array
    {
        $interestingHeaders = [
            'Content-Type',
            'Content-Length',
            'Cache-Control',
            'ETag',
            'Last-Modified',
            'Content-Encoding',
            'Content-Disposition',
        ];

        $highlights = [];

        foreach ($interestingHeaders as $headerName) {
            $value = $response->headers->get($headerName);

            if (is_string($value) && $value !== '') {
                $highlights[$headerName] = $value;
            }
        }

        return $highlights;
    }

    protected function classifyResponse(Response $response, string $contentType): string
    {
        $normalizedType = strtolower($contentType);
        $contentDisposition = strtolower((string) ($response->headers->get('Content-Disposition') ?? ''));

        if ($response->isRedirection()) {
            return 'Redirect';
        }

        if ($contentDisposition !== '' && str_contains($contentDisposition, 'attachment')) {
            return 'File Download';
        }

        if (
            str_contains($normalizedType, 'application/octet-stream')
            || str_contains($normalizedType, 'application/pdf')
            || str_contains($normalizedType, 'application/zip')
            || str_contains($normalizedType, 'image/')
            || str_contains($normalizedType, 'audio/')
            || str_contains($normalizedType, 'video/')
        ) {
            return 'File Response';
        }

        if ($response->isInformational() || $response->isEmpty() || (($response->body ?? '') === '' && $response->getStatusCode() === 204)) {
            return 'Empty Response';
        }

        if (str_contains($normalizedType, 'application/json') || str_contains($normalizedType, '+json')) {
            return 'JSON API';
        }

        if (str_contains($normalizedType, 'text/html')) {
            return 'HTML Page';
        }

        if (str_starts_with($normalizedType, 'text/')) {
            return 'Text Response';
        }

        if ($response->getStatusCode() >= 400) {
            return 'Error Response';
        }

        return 'HTTP Response';
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: bool}
     */
    protected function buildBodyPreview(Response $response, string $contentType): array
    {
        if (! $this->shouldCapturePreview($response, $contentType)) {
            return [null, null, false];
        }

        $preview = $this->stringifyPreviewSource($response, $contentType);

        if ($preview === null || $preview === '') {
            return [null, null, false];
        }

        return [$preview, $this->detectPreviewFormat($contentType), false];
    }

    protected function shouldCapturePreview(Response $response, string $contentType): bool
    {
        $normalizedType = strtolower($contentType);
        $contentDisposition = strtolower((string) ($response->headers->get('Content-Disposition') ?? ''));

        if ($contentDisposition !== '' && str_contains($contentDisposition, 'attachment')) {
            return false;
        }

        if ($normalizedType === '') {
            return ($response->body ?? '') !== '';
        }

        if (
            str_contains($normalizedType, 'application/octet-stream')
            || str_contains($normalizedType, 'application/pdf')
            || str_contains($normalizedType, 'application/zip')
            || str_contains($normalizedType, 'image/')
            || str_contains($normalizedType, 'audio/')
            || str_contains($normalizedType, 'video/')
        ) {
            return false;
        }

        return true;
    }

    protected function stringifyPreviewSource(Response $response, string $contentType): ?string
    {
        $body = $response->body ?? '';

        if ($body !== '') {
            return $this->sanitizePreview($body, $contentType);
        }

        $original = $response->getOriginal();

        if ($original === null) {
            return null;
        }

        if (is_string($original)) {
            return $this->sanitizePreview($original, $contentType);
        }

        if (is_scalar($original)) {
            return (string) $original;
        }

        $encoded = json_encode($original, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $this->sanitizePreview($encoded, $contentType);
    }

    protected function sanitizePreview(string $body, string $contentType): string
    {
        $sanitizer = $this->sanitizer();
        $sanitized = $sanitizer->sanitizeJsonOrText($body);

        if (is_array($sanitized)) {
            $encoded = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded === false ? '' : $encoded;
        }

        return $this->prettyBodyIfJson((string) $sanitized, $contentType);
    }

    protected function prettyBodyIfJson(string $body, string $contentType): string
    {
        $normalizedType = strtolower($contentType);

        if (! str_contains($normalizedType, 'application/json') && ! str_contains($normalizedType, '+json')) {
            return $body;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $body;
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? $body : $encoded;
    }

    protected function detectPreviewFormat(string $contentType): ?string
    {
        $normalizedType = strtolower($contentType);

        if (str_contains($normalizedType, 'application/json') || str_contains($normalizedType, '+json')) {
            return 'json';
        }

        if (str_contains($normalizedType, 'text/html')) {
            return 'html';
        }

        if (str_starts_with($normalizedType, 'text/')) {
            return 'text';
        }

        if (str_contains($normalizedType, 'xml')) {
            return 'xml';
        }

        return null;
    }
}
