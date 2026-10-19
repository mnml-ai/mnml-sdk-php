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
        public readonly int $creditsRefunded,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            outcome: (string) ($d['outcome'] ?? ''),
            creditsRefunded: (int) ($d['credits_refunded'] ?? 0),
        );
    }
}
