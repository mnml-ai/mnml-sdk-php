<?php

declare(strict_types=1);

namespace Mnml\Tests;

use Mnml\Exception\WebhookVerificationException;
use Mnml\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const NOW = 1_760_000_000;

    /** A throwaway key made fresh for every run: no secret, real or sample, lives in this repo. */
    private static string $key;

    private static string $secret;

    public static function setUpBeforeClass(): void
    {
        self::$key = random_bytes(24);
        self::$secret = 'whsec_' . base64_encode(self::$key);
    }

    private static function body(): string
    {
        return '{"type":"job.succeeded","timestamp":"2026-10-18T09:00:40Z","data":' . FakeTransport::JOB . '}';
    }

    /** @return array<string, string> */
    private static function headers(string $sig, int $ts = self::NOW): array
    {
        return ['Webhook-Id' => 'msg_1', 'Webhook-Timestamp' => (string) $ts, 'Webhook-Signature' => $sig];
    }

    public function testASignedDeliveryVerifiesAndReadsAsAJob(): void
    {
        $sig = Webhooks::sign(self::$secret, 'msg_1', self::NOW, self::body());

        $event = Webhooks::verify(self::body(), self::headers($sig), self::$secret, self::NOW);

        self::assertSame('job.succeeded', $event->type);
        self::assertSame('42', $event->job()?->id);
        self::assertNull($event->creditsLow());
    }

    public function testCreditsLowReadsInDollars(): void
    {
        $body = '{"type":"credits.low","timestamp":"2026-11-09T09:00:00Z",'
            . '"data":{"balance_usd":0.396,"threshold_usd":0.99,"balance":40,"threshold":100}}';
        $sig = Webhooks::sign(self::$secret, 'msg_1', self::NOW, $body);

        $low = Webhooks::verify($body, self::headers($sig), self::$secret, self::NOW)->creditsLow();

        self::assertNotNull($low);
        self::assertSame(0.396, $low->balanceUsd);
        self::assertSame(0.99, $low->thresholdUsd);
        self::assertSame(40, $low->balance);
    }

    public function testAnyOfSeveralSignaturesMayMatchAndPsr7HeadersWork(): void
    {
        $sig = Webhooks::sign(self::$secret, 'msg_1', self::NOW, self::body());
        $psr7 = array_map(static fn (string $v) => [$v], self::headers('v1,c29tZXRoaW5n ' . $sig));

        self::assertSame('job.succeeded', Webhooks::verify(self::body(), $psr7, self::$secret, self::NOW)->type);
    }

    public function testAChangedBodyIsRefused(): void
    {
        $sig = Webhooks::sign(self::$secret, 'msg_1', self::NOW, self::body());

        $this->expectException(WebhookVerificationException::class);
        Webhooks::verify(str_replace('42', '43', self::body()), self::headers($sig), self::$secret, self::NOW);
    }

    public function testAnOldDeliveryIsRefused(): void
    {
        $old = self::NOW - 600;
        $sig = Webhooks::sign(self::$secret, 'msg_1', $old, self::body());

        $this->expectException(WebhookVerificationException::class);
        Webhooks::verify(self::body(), self::headers($sig, $old), self::$secret, self::NOW);
    }

    public function testMissingHeadersAreRefused(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Webhooks::verify(self::body(), [], self::$secret, self::NOW);
    }

    public function testTheSignatureIsTheStandardWebhooksHmac(): void
    {
        // HMAC-SHA256 of id.timestamp.body under the decoded secret; the whsec_ prefix is optional.
        $expected = 'v1,' . base64_encode(hash_hmac('sha256', 'msg_1.1614265330.{"test":1}', self::$key, true));

        self::assertSame($expected, Webhooks::sign(self::$secret, 'msg_1', 1614265330, '{"test":1}'));
        self::assertSame($expected, Webhooks::sign(base64_encode(self::$key), 'msg_1', 1614265330, '{"test":1}'));
    }
}
