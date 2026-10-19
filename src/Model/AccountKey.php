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
        /** Your own daily limit for this key, or null for none. */
        public readonly ?int $dailyCreditLimit,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            allowedOrigins: array_values(array_map('strval', $d['allowed_origins'] ?? [])),
            dailyCreditLimit: isset($d['daily_credit_limit']) ? (int) $d['daily_credit_limit'] : null,
        );
    }
}
