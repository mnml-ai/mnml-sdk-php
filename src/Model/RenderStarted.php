<?php

declare(strict_types=1);

namespace Mnml\Model;

/** What a render answers: every job it started (one per count). */
final class RenderStarted
{
    public function __construct(
        /** The first job's id. */
        public readonly string $id,
        /** @var list<string> Every job the call started. */
        public readonly array $ids,
        public readonly string $status,
        public readonly int $creditsCharged,
        public readonly bool $replayed,
        /** @var list<string> */
        public readonly array $notes,
        /** @var list<Job>|null Only with a wait: every job as it stood when the wait ended. */
        public readonly ?array $jobs,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            ids: array_values(array_map('strval', $d['ids'] ?? [])),
            status: (string) ($d['status'] ?? ''),
            creditsCharged: (int) ($d['credits_charged'] ?? 0),
            replayed: (bool) ($d['replayed'] ?? false),
            notes: array_values(array_map('strval', $d['notes'] ?? [])),
            jobs: isset($d['jobs']) ? array_values(array_map([Job::class, 'fromArray'], $d['jobs'] ?? [])) : null,
        );
    }
}
