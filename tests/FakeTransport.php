<?php

declare(strict_types=1);

namespace Mnml\Tests;

use Mnml\Client;
use Mnml\Transport;
use Mnml\TransportException;

/** Answers from a queue and records every request it saw. */
final class FakeTransport implements Transport
{
    public const JOB = '{"id":"42","status":"succeeded","kind":"render","engine":"v4.6-ultra",'
        . '"outputs":[{"url":"https://api.test/v2/files/42?exp=1&sig=x","media":"image","expires_at":"2026-10-18T10:00:00Z"}],'
        . '"charged_usd":0.297,"refunded_usd":0,"credits_charged":30,"credits_refunded":0,"error":null,"created_at":"2026-10-18T09:00:00Z","completed_at":"2026-10-18T09:00:40Z"}';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];
    /** @var list<float> */
    public array $pauses = [];
    /** @var list<callable(): array> */
    private array $answers = [];

    public static function jobWith(string $status): string
    {
        return str_replace('"succeeded"', "\"{$status}\"", self::JOB);
    }

    /** @param array<string, string> $headers */
    public function answer(int $status, string $body, array $headers = []): self
    {
        $this->answers[] = static fn () => [
            'status' => $status,
            'headers' => array_change_key_case($headers + ['X-Request-Id' => 'req_test', 'Content-Type' => 'application/json']),
            'body' => $body,
        ];
        return $this;
    }

    public function ok(string $data): self
    {
        return $this->answer(200, '{"success":true,"data":' . $data . '}');
    }

    /** @param array<string, string> $headers */
    public function fail(int $status, string $code, string $message = 'No.', array $headers = []): self
    {
        return $this->answer($status, json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message]]), $headers);
    }

    public function drop(): self
    {
        $this->answers[] = static fn () => throw new TransportException('connection reset');
        return $this;
    }

    public function client(int $maxRetries = 2): Client
    {
        return (new Client(['api_key' => 'test-key', 'base_url' => 'https://api.test', 'max_retries' => $maxRetries, 'transport' => $this]))
            ->withSleep(fn (float $s) => $this->pauses[] = $s);
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => array_change_key_case($headers), 'body' => $body];
        if ($this->answers === []) {
            throw new \LogicException("No answer queued for {$method} {$url}");
        }

        return array_shift($this->answers)();
    }

    public function stream(string $method, string $url, array $headers, float $timeout): array
    {
        $res = $this->send($method, $url, $headers, null, $timeout);

        return ['status' => $res['status'], 'headers' => $res['headers'], 'lines' => explode("\n", $res['body'])];
    }
}
