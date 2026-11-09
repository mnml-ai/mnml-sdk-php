<?php

declare(strict_types=1);

namespace Mnml\Model;

/** An engine's prices: renderUsd and editUsd, in US dollars. */
final class EnginePrices
{
    public function __construct(
        /** @deprecated Read $renderUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $render,
        /** @deprecated Read $editUsd: the same amount in API credits ($0.0099 each). A later release removes it. */
        public readonly int $edit,
        /** One render, in US dollars (four decimals). */
        public readonly float $renderUsd = 0.0,
        /** One edit, in US dollars (four decimals). */
        public readonly float $editUsd = 0.0,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            render: (int) ($d['render'] ?? 0),
            edit: (int) ($d['edit'] ?? 0),
            renderUsd: (float) ($d['render_usd'] ?? 0),
            editUsd: (float) ($d['edit_usd'] ?? 0),
        );
    }
}
