<?php

declare(strict_types=1);

namespace Mnml\Model;

/** An engine's prices, in credits. */
final class EnginePrices
{
    public function __construct(
        public readonly int $render,
        public readonly int $edit,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            render: (int) ($d['render'] ?? 0),
            edit: (int) ($d['edit'] ?? 0),
        );
    }
}
