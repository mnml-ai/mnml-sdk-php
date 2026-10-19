<?php

declare(strict_types=1);

namespace Mnml\Model;

/** One field the API refused, on a VALIDATION_FAILED answer. */
final class Issue
{
    public function __construct(
        /** The field, as a dotted path (references.0.mode). */
        public readonly string $path,
        public readonly string $message,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            path: (string) ($d['path'] ?? ''),
            message: (string) ($d['message'] ?? ''),
        );
    }
}
