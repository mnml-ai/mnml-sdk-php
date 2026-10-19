<?php

declare(strict_types=1);

namespace Mnml;

use Mnml\Model\CreditsLow;
use Mnml\Model\Job;

/**
 * A webhook message. $type is job.succeeded, job.failed, job.canceled, credits.low or
 * webhook.test; new types may be added, so ignore one you do not handle.
 */
final class WebhookEvent
{
    /** @param array<string, mixed> $data The event's data, as sent. */
    public function __construct(
        public readonly string $type,
        public readonly string $timestamp,
        public readonly array $data,
    ) {
    }

    /** For a job.* event: the job, as Jobs::get returns it. */
    public function job(): ?Job
    {
        return str_starts_with($this->type, 'job.') ? Job::fromArray($this->data) : null;
    }

    /** For credits.low: the balance and the threshold it fell under. */
    public function creditsLow(): ?CreditsLow
    {
        return $this->type === 'credits.low' ? CreditsLow::fromArray($this->data) : null;
    }
}
