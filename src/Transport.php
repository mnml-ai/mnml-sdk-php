<?php

declare(strict_types=1);

namespace Mnml;

/**
 * How the client sends a request. The built-in {@see StreamTransport} needs nothing but PHP; pass
 * your own to route through Guzzle, a proxy or a test double.
 */
interface Transport
{
    /**
     * Sends one request and reads the whole answer.
     *
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string} Header names in lower case.
     * @throws TransportException When no answer came (a dropped connection, a timeout).
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array;

    /**
     * Sends one request and returns the answer's lines as they arrive (a Server-Sent Events stream).
     *
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, lines: iterable<string>}
     * @throws TransportException When no answer came.
     */
    public function stream(string $method, string $url, array $headers, float $timeout): array;
}
