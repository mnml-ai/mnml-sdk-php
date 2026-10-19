<?php

declare(strict_types=1);

namespace Mnml;

/**
 * The built-in transport: PHP's own https:// stream wrapper, so the SDK needs no extension or
 * package beyond a standard PHP build (it uses OpenSSL for TLS).
 */
final class StreamTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        [$handle, $status, $responseHeaders] = $this->open($method, $url, $headers, $body, $timeout);
        $content = stream_get_contents($handle);
        $meta = stream_get_meta_data($handle);
        fclose($handle);
        if ($content === false || $meta['timed_out']) {
            throw new TransportException("The request to {$url} did not finish within {$timeout} s.");
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $content];
    }

    public function stream(string $method, string $url, array $headers, float $timeout): array
    {
        [$handle, $status, $responseHeaders] = $this->open($method, $url, $headers, null, $timeout);
        // No time limit between lines once the answer started: the API sends a ping every 15 s.
        stream_set_timeout($handle, 60);
        $lines = (static function () use ($handle): \Generator {
            try {
                while (($line = fgets($handle)) !== false) {
                    yield rtrim($line, "\r\n");
                }
            } finally {
                fclose($handle);
            }
        })();

        return ['status' => $status, 'headers' => $responseHeaders, 'lines' => $lines];
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: resource, 1: int, 2: array<string, string>}
     */
    private function open(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $lines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true, // a 4xx or 5xx is an answer, read like any other
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
        ]);
        $error = null;
        set_error_handler(static function (int $_, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $handle = fopen($url, 'rb', false, $context);
        } finally {
            restore_error_handler();
        }
        if ($handle === false) {
            throw new TransportException("No answer from {$url}: " . ($error ?? 'the connection failed') . '.');
        }

        $meta = stream_get_meta_data($handle);
        $raw = $meta['wrapper_data'] ?? [];
        $status = 0;
        $responseHeaders = [];
        foreach (is_array($raw) ? $raw : [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                // A new status line starts a new answer (after a 100 Continue).
                $status = (int) $m[1];
                $responseHeaders = [];
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $responseHeaders[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
            }
        }

        return [$handle, $status, $responseHeaders];
    }
}
