<?php

declare(strict_types=1);

namespace Mnml;

/** The job statuses. Succeeded, failed and canceled are final. */
final class JobStatus
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CANCELED = 'canceled';

    public static function isSettled(string $status): bool
    {
        return in_array($status, [self::SUCCEEDED, self::FAILED, self::CANCELED], true);
    }
}
