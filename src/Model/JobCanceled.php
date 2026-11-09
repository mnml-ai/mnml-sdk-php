<?php

declare(strict_types=1);

namespace Mnml\Model;

/** What a cancel answers. */
final class JobCanceled
{
    public function __construct(
        public readonly string $id,
        /** refunded, requested, too-late or already-settled. */
        public readonly string $outcome,
        /** @deprecated Read $refundedUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $creditsRefunded,
        /** What came back to the balance, in US dollars (four decimals). */
        public readonly float $refundedUsd = 0.0,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            outcome: (string) ($d['outcome'] ?? ''),
            creditsRefunded: (int) ($d['credits_refunded'] ?? 0),
            refundedUsd: (float) ($d['refunded_usd'] ?? 0),
        );
    }
}
