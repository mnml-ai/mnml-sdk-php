<?php

declare(strict_types=1);

namespace Mnml\Model;

/** credits.low: the balance fell under the threshold (sent at most once a day). */
final class CreditsLow
{
    public function __construct(
        public readonly int $balance,
        public readonly int $threshold,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            balance: (int) ($d['balance'] ?? 0),
            threshold: (int) ($d['threshold'] ?? 0),
        );
    }
}
