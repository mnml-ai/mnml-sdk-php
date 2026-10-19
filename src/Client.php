<?php

declare(strict_types=1);

namespace Mnml;

use Mnml\Exception\MnmlException;
use Mnml\Model\Issue;
use Mnml\Resource\Account;
use Mnml\Resource\Edits;
use Mnml\Resource\Engines;
use Mnml\Resource\Enhancements;
use Mnml\Resource\Files;
use Mnml\Resource\Jobs;
use Mnml\Resource\Renders;
use Mnml\Resource\Videos;

/**
 * The mnml API client. Every call returns the answer's data; a refusal throws
 * {@see MnmlException} with the API's code.
 *
 *     $mnml = new Mnml\Client(); // reads MNML_API_KEY
 *     $jobs = $mnml->renders->createAndWait([
 *         'mode' => Mnml\Mode::EXTERIOR,
 *         'image' => Mnml\Image::fromFile('massing.png'),
 *         'prompt' => 'Timber facade, late afternoon light',
 *     ]);
 *     echo $jobs[0]->outputs[0]->url;
 */
final class Client
{
    public const VERSION = '0.1.0';

    /** The longest the API holds an answer (wait), under Cloudflare's 100-second limit. */
    public const MAX_WAIT_SECONDS = 90;

    /** A longer Retry-After (a daily limit runs to midnight UTC) is the caller's to handle. */
    private const MAX_RETRY_AFTER = 60.0;

    public readonly Renders $renders;
    public readonly Edits $edits;
    public readonly Enhancements $enhancements;
    public readonly Videos $videos;
    public readonly Jobs $jobs;
    public readonly Files $files;
    public readonly Account $account;
    public readonly Engines $engines;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $maxRetries;
    private readonly float $timeout;
    private readonly Transport $transport;

    /** @var callable(float): void The pause between retries and between reads; tests replace it. */
    private $sleep;

    /**
     * @param array{api_key?: string, base_url?: string, max_retries?: int, timeout?: float, transport?: Transport} $options
     *   api_key defaults to the MNML_API_KEY environment variable; max_retries (default 2) covers a
     *   429, a 5xx or a dropped connection; timeout is per request, in seconds (default 60).
     */
    public function __construct(array $options = [])
    {
        $key = $options['api_key'] ?? (getenv('MNML_API_KEY') ?: null);
        if (!is_string($key) || trim($key) === '') {
            throw new \InvalidArgumentException('Pass api_key, or set the MNML_API_KEY environment variable.');
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.mnml.ai', '/');
        $this->maxRetries = max(0, $options['max_retries'] ?? 2);
        $this->timeout = (float) ($options['timeout'] ?? 60.0);
        $this->transport = $options['transport'] ?? new StreamTransport();
        $this->sleep = static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };

        $this->renders = new Renders($this);
        $this->edits = new Edits($this);
        $this->enhancements = new Enhancements($this);
        $this->videos = new Videos($this);
        $this->jobs = new Jobs($this);
        $this->files = new Files($this);
        $this->account = new Account($this);
        $this->engines = new Engines($this);
    }

    /** @internal For tests: what to do instead of sleeping. */
    public function withSleep(callable $sleep): self
    {
        $this->sleep = $sleep;
        return $this;
    }

    /** @internal */
    public function pause(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /** @internal */
    public static function waitPath(string $path, ?int $wait): string
    {
        if ($wait === null) {
            return $path;
        }
        if ($wait < 1 || $wait > self::MAX_WAIT_SECONDS) {
            throw new \InvalidArgumentException('wait is 1 to ' . self::MAX_WAIT_SECONDS . " seconds, not {$wait}.");
        }

        return "{$path}?wait={$wait}";
    }

    /**
     * @internal A JSON call, answered with the envelope's data or thrown as MnmlException.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null, int $wait = 0): array
    {
        $headers = $this->headers(true, 'application/json');
        if ($method === 'POST') {
            // One key per call, kept across the client's own retries, so a retry never charges twice.
            $headers['Idempotency-Key'] = $idempotencyKey ?? self::uuid();
        }
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }

        $res = $this->send(fn () => $this->transport->send($method, $this->baseUrl . $path, $headers, $payload, $this->timeout + $wait));
        $json = json_decode($res['body'], true);
        if ($res['status'] < 300 && is_array($json) && ($json['success'] ?? false) === true && is_array($json['data'] ?? null)) {
            return $json['data'];
        }
        throw self::errorFrom($res['status'], $res['headers'], is_array($json) ? $json : null);
    }

    /**
     * @internal An event stream's lines, opened with the client's retries.
     *
     * @return array{status: int, headers: array<string, string>, lines: iterable<string>}
     */
    public function openStream(string $path): array
    {
        $res = $this->send(fn () => $this->transport->stream('GET', $this->baseUrl . $path, $this->headers(true, 'text/event-stream'), $this->timeout));
        if ($res['status'] >= 300) {
            $body = '';
            foreach ($res['lines'] as $line) {
                $body .= $line . "\n";
            }
            $json = json_decode($body, true);
            throw self::errorFrom($res['status'], $res['headers'], is_array($json) ? $json : null);
        }

        return $res;
    }

    /**
     * @internal A download: the link's signature is the credential, so no key is sent.
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function download(string $url): array
    {
        $res = $this->send(fn () => $this->transport->send('GET', $url, $this->headers(false, '*/*'), null, $this->timeout));
        if ($res['status'] >= 300) {
            $json = json_decode($res['body'], true);
            throw self::errorFrom($res['status'], $res['headers'], is_array($json) ? $json : null);
        }

        return $res;
    }

    /** @return array<string, string> */
    private function headers(bool $authorized, string $accept): array
    {
        $headers = ['User-Agent' => 'mnml-sdk-php/' . self::VERSION, 'Accept' => $accept];
        if ($authorized) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }

    /**
     * One call with the client's retries: a 429, a 5xx or a dropped connection is sent again (the
     * same headers, so the same idempotency key) after Retry-After or a backoff. The last answer is
     * returned, whatever it is.
     *
     * @template T of array{status: int, headers: array<string, string>}
     * @param callable(): T $attempt
     * @return T
     */
    private function send(callable $attempt): array
    {
        for ($n = 0; ; $n++) {
            try {
                $res = $attempt();
            } catch (TransportException $e) {
                if ($n >= $this->maxRetries) {
                    throw $e;
                }
                $this->pause(self::backoff($n));
                continue;
            }
            if (in_array($res['status'], [429, 500, 502, 503, 504], true) && $n < $this->maxRetries) {
                $wait = self::retryAfter($res['headers']) ?? self::backoff($n);
                if ($wait <= self::MAX_RETRY_AFTER) {
                    $this->pause($wait);
                    continue;
                }
            }

            return $res;
        }
    }

    /** @param array<string, string> $headers */
    private static function retryAfter(array $headers): ?float
    {
        $value = $headers['retry-after'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value > 0 ? (float) $value : null;
        }
        $at = strtotime($value);

        return $at !== false && $at > time() ? (float) ($at - time()) : null;
    }

    /** 0.5 s, 1 s, 2 s … with a little jitter. */
    private static function backoff(int $attempt): float
    {
        return 0.5 * (2 ** $attempt) + random_int(0, 249) / 1000;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    private static function errorFrom(int $status, array $headers, ?array $json): MnmlException
    {
        $error = ($json !== null && ($json['success'] ?? null) === false && is_array($json['error'] ?? null)) ? $json['error'] : [];

        return new MnmlException(
            is_string($error['code'] ?? null) ? $error['code'] : 'HTTP_ERROR',
            is_string($error['message'] ?? null) ? $error['message'] : "The API answered {$status}.",
            $status,
            $headers['x-request-id'] ?? null,
            is_array($error['details'] ?? null) ? $error['details'] : null,
            array_values(array_map([Issue::class, 'fromArray'], is_array($error['issues'] ?? null) ? $error['issues'] : [])),
        );
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
