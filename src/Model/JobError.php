<?php

declare(strict_types=1);

namespace Mnml\Model;

/** Why a job failed. */
final class JobError
{
    public function __construct(
        /** UNSAFE_CONTENT, NO_CHANGE, CANCELED or RENDER_FAILED. */
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            code: (string) ($d['code'] ?? ''),
            message: (string) ($d['message'] ?? ''),
        );
    }
}
