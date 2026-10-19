<?php

declare(strict_types=1);

namespace Mnml\Model;

/** A render engine. */
final class Engine
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $tier,
        public readonly bool $default,
        public readonly bool $paidOnly,
        /** Whether this account may use it now (a paid-only engine needs a paid account). */
        public readonly bool $available,
        public readonly EnginePrices $prices,
        public readonly EngineCapabilities $capabilities,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            tier: (string) ($d['tier'] ?? ''),
            default: (bool) ($d['default'] ?? false),
            paidOnly: (bool) ($d['paid_only'] ?? false),
            available: (bool) ($d['available'] ?? false),
            prices: EnginePrices::fromArray($d['prices'] ?? []),
            capabilities: EngineCapabilities::fromArray($d['capabilities'] ?? []),
        );
    }
}
