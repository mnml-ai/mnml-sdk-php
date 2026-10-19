<?php

declare(strict_types=1);

namespace Mnml\Model;

/** The balance. */
final class AccountCredits
{
    public function __construct(
        /** What the next call can spend. */
        public readonly int $spendable,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            spendable: (int) ($d['spendable'] ?? 0),
        );
    }
}
