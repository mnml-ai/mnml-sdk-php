<?php

declare(strict_types=1);

namespace Mnml\Model;

/** The key's account: balance, tier and limits. */
final class Account
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name,
        /** paid once the account has topped up its API wallet; it sets the limits. */
        public readonly string $tier,
        public readonly AccountCredits $credits,
        public readonly AccountKey $key,
        public readonly AccountLimits $limits,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            email: (string) ($d['email'] ?? ''),
            name: isset($d['name']) ? (string) $d['name'] : null,
            tier: (string) ($d['tier'] ?? ''),
            credits: AccountCredits::fromArray($d['credits'] ?? []),
            key: AccountKey::fromArray($d['key'] ?? []),
            limits: AccountLimits::fromArray($d['limits'] ?? []),
        );
    }
}
