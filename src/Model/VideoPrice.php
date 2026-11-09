<?php

declare(strict_types=1);

namespace Mnml\Model;

/** A video price, per length. */
final class VideoPrice
{
    public function __construct(
        /** The clip length, or null for the model's one fixed length. */
        public readonly ?int $durationSeconds,
        /** @deprecated Read $usd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $credits,
        /** One clip of this length, in US dollars (four decimals). */
        public readonly float $usd = 0.0,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            durationSeconds: isset($d['duration_seconds']) ? (int) $d['duration_seconds'] : null,
            credits: (int) ($d['credits'] ?? 0),
            usd: (float) ($d['usd'] ?? 0),
        );
    }
}
