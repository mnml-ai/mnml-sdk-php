<?php

declare(strict_types=1);

namespace Mnml\Model;

/** The limits a key works under. */
final class AccountLimits
{
    public function __construct(
        /** account (shared by every key, free) or key (this key's own, paid). */
        public readonly string $scope,
        public readonly int $requestsPerMinute,
        /** GET requests (job polls included), counted apart from the rest. */
        public readonly int $readsPerMinute,
        public readonly int $concurrentJobs,
        /** Credits this key may still spend per UTC day; null is no limit. */
        public readonly ?int $dailyCredits,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            scope: (string) ($d['scope'] ?? ''),
            requestsPerMinute: (int) ($d['requests_per_minute'] ?? 0),
            readsPerMinute: (int) ($d['reads_per_minute'] ?? 0),
            concurrentJobs: (int) ($d['concurrent_jobs'] ?? 0),
            dailyCredits: isset($d['daily_credits']) ? (int) $d['daily_credits'] : null,
        );
    }
}
