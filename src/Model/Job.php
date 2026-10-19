<?php

declare(strict_types=1);

namespace Mnml\Model;

use Mnml\JobStatus;

/** A job: its status and, once it succeeded, its outputs. */
final class Job
{
    public function __construct(
        public readonly string $id,
        /** One of JobStatus. */
        public readonly string $status,
        /** What the job does: render, edit, upscale, video and so on. */
        public readonly string $kind,
        public readonly ?string $engine,
        /** @var list<JobOutput> Signed links that expire: download what you keep. */
        public readonly array $outputs,
        public readonly int $creditsCharged,
        public readonly int $creditsRefunded,
        /** Why it failed, or null. */
        public readonly ?JobError $error,
        /** ISO 8601. */
        public readonly string $createdAt,
        /** ISO 8601, or null while it runs. */
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            status: (string) ($d['status'] ?? ''),
            kind: (string) ($d['kind'] ?? ''),
            engine: isset($d['engine']) ? (string) $d['engine'] : null,
            outputs: array_values(array_map([JobOutput::class, 'fromArray'], $d['outputs'] ?? [])),
            creditsCharged: (int) ($d['credits_charged'] ?? 0),
            creditsRefunded: (int) ($d['credits_refunded'] ?? 0),
            error: isset($d['error']) ? JobError::fromArray($d['error']) : null,
            createdAt: (string) ($d['created_at'] ?? ''),
            completedAt: isset($d['completed_at']) ? (string) $d['completed_at'] : null,
        );
    }

    /** Whether the job is done: succeeded, failed or canceled. */
    public function isSettled(): bool
    {
        return JobStatus::isSettled($this->status);
    }
}
