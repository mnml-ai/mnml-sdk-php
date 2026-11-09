<?php

declare(strict_types=1);

namespace Mnml\Model;

/** The key a call used. */
final class AccountKey
{
    public function __construct(
        public readonly string $id,
        /** @var list<string> */
        public readonly array $allowedOrigins,
        /** @deprecated Read $dailyLimitUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly ?int $dailyCreditLimit,
        /** Your own daily limit for this key in US dollars, or null for none. */
        public readonly ?float $dailyLimitUsd = null,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            allowedOrigins: array_values(array_map('strval', $d['allowed_origins'] ?? [])),
            dailyCreditLimit: isset($d['daily_credit_limit']) ? (int) $d['daily_credit_limit'] : null,
            dailyLimitUsd: isset($d['daily_limit_usd']) ? (float) $d['daily_limit_usd'] : null,
        );
    }
}
