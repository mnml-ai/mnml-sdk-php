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
        public readonly int $creditsCharged,
        /** True when this answers an earlier identical request: nothing new was charged. */
        public readonly bool $replayed,
        /** @var list<string> What the API changed or dropped from the request. */
        public readonly array $notes,
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
        );
    }
}
