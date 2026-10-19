<?php

declare(strict_types=1);

namespace Mnml\Model;

/**
 * One event of Jobs::stream: the job as it stands (job), then exactly one last event: done (the
 * job settled), timeout (still running after ten minutes: open the stream again) or error (the API
 * could not read the job; it still runs).
 */
final class JobStreamEvent
{
    public function __construct(
        public readonly string $type,
        public readonly ?Job $job = null,
        public readonly ?JobError $error = null,
    ) {
    }

    /** Whether this is the stream's last event. */
    public function isFinal(): bool
    {
        return $this->type !== 'job';
    }
}
