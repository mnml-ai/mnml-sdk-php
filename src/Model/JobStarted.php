<?php

declare(strict_types=1);

namespace Mnml\Model;

/** What a create call answers: the job it started. */
final class JobStarted
{
    public function __construct(
        public readonly string $id,
        /** One of JobStatus. */
        public readonly string $status,
        /** @deprecated Read $chargedUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $creditsCharged,
        /** True when this answers an earlier identical request: nothing new was charged. */
        public readonly bool $replayed,
        /** @var list<string> What the API changed or dropped from the request. */
        public readonly array $notes,
        /** What the call charged, in US dollars (four decimals). */
        public readonly float $chargedUsd = 0.0,
        /** The price at list, before your account's API discount, in US dollars. */
        public readonly float $listUsd = 0.0,
        /** Your account's API discount on this call, in percent (35.39 means 35.39 % off); 0 when none. */
        public readonly float $discountPercent = 0.0,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            status: (string) ($d['status'] ?? ''),
            creditsCharged: (int) ($d['credits_charged'] ?? 0),
            replayed: (bool) ($d['replayed'] ?? false),
            notes: array_values(array_map('strval', $d['notes'] ?? [])),
            chargedUsd: (float) ($d['charged_usd'] ?? 0),
            listUsd: (float) ($d['list_usd'] ?? 0),
            discountPercent: (float) ($d['discount_percent'] ?? 0),
        );
    }
}
