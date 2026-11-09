<?php

declare(strict_types=1);

namespace Mnml\Model;

/**
 * The balance, in credits.
 *
 * @deprecated Read Account::$balanceUsd. A later release removes it.
 */
final class AccountCredits
{
    public function __construct(
        /** @deprecated Read Account::$balanceUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
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
