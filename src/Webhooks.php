<?php

declare(strict_types=1);

namespace Mnml;

use Mnml\Exception\WebhookVerificationException;

/**
 * Verifies webhook deliveries: the Standard Webhooks scheme the API signs with (webhook-id,
 * webhook-timestamp, webhook-signature, HMAC-SHA256 of id.timestamp.body under your whsec_
 * secret). Pass the RAW body, the exact bytes received, or the check fails.
 *
 *     $event = Mnml\Webhooks::verify(file_get_contents('php://input'), getallheaders(), $secret);
 *     if ($event->type === 'job.succeeded') { $job = $event->job(); }
 */
final class Webhooks
{
    /** How far a delivery's timestamp may be from now, in seconds. */
    public const TOLERANCE = 300;

    /**
     * @param array<string, string|list<string>> $headers Names in any case; a PSR-7 getHeaders() works too.
     * @throws WebhookVerificationException When the signature or the timestamp does not hold.
     */
    public static function verify(string $rawBody, array $headers, string $secret, ?int $now = null): WebhookEvent
    {
        $h = [];
        foreach ($headers as $name => $value) {
            $h[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        $id = $h['webhook-id'] ?? '';
        $ts = $h['webhook-timestamp'] ?? '';
        $sig = $h['webhook-signature'] ?? '';
        if ($id === '' || $ts === '' || $sig === '') {
            throw new WebhookVerificationException('Missing webhook headers.');
        }
        if (!ctype_digit($ts) || abs(($now ?? time()) - (int) $ts) > self::TOLERANCE) {
            throw new WebhookVerificationException('Webhook timestamp is too old or in the future.');
        }
        $expected = self::sign($secret, $id, (int) $ts, $rawBody);
        $ok = false;
        // The header may carry several space-separated signatures (during a rotation).
        foreach (explode(' ', $sig) as $candidate) {
            $ok = hash_equals($expected, $candidate) || $ok;
        }
        if (!$ok) {
            throw new WebhookVerificationException('Webhook signature does not match.');
        }
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new WebhookVerificationException('The webhook body is not JSON.');
        }

        return new WebhookEvent((string) ($event['type'] ?? ''), (string) ($event['timestamp'] ?? ''), is_array($event['data'] ?? null) ? $event['data'] : []);
    }

    /** The webhook-signature value for a delivery: "v1,<base64>". */
    public static function sign(string $secret, string $id, int $timestamp, string $body): string
    {
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            throw new \InvalidArgumentException('The webhook secret is not base64 after whsec_.');
        }

        return 'v1,' . base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));
    }
}
