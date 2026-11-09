<?php

declare(strict_types=1);

namespace Mnml\Model;

/** credits.low: the balance fell under the threshold (sent at most once a day). */
final class CreditsLow
{
    public function __construct(
        /** @deprecated Read $balanceUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $balance,
        /** @deprecated Read $thresholdUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $threshold,
        /** The balance now, in US dollars (four decimals). */
        public readonly float $balanceUsd = 0.0,
        /** The line it fell under, in US dollars. */
        public readonly float $thresholdUsd = 0.0,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            balance: (int) ($d['balance'] ?? 0),
            threshold: (int) ($d['threshold'] ?? 0),
            balanceUsd: (float) ($d['balance_usd'] ?? 0),
            thresholdUsd: (float) ($d['threshold_usd'] ?? 0),
        );
    }
}
