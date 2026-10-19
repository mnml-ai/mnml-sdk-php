<?php

declare(strict_types=1);

namespace Mnml\Exception;

/** Jobs::wait ran out of time before the job settled; the job keeps running. */
final class TimeoutException extends \RuntimeException
{
    public function __construct(public readonly string $jobId)
    {
        parent::__construct("Job {$jobId} did not finish in time. It is still running; read it again later.");
    }
}
