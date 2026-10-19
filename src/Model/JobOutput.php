<?php

declare(strict_types=1);

namespace Mnml\Model;

/** One result of a job. */
final class JobOutput
{
    public function __construct(
        /** A signed link, served by the API. Download it before expires_at. */
        public readonly string $url,
        /** image or video. */
        public readonly string $media,
        /** When the link stops working (ISO 8601). */
        public readonly string $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            url: (string) ($d['url'] ?? ''),
            media: (string) ($d['media'] ?? ''),
            expiresAt: (string) ($d['expires_at'] ?? ''),
        );
    }
}
