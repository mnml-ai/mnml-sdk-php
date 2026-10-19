<?php

declare(strict_types=1);

namespace Mnml\Model;

/** What an engine can do. */
final class EngineCapabilities
{
    public function __construct(
        public readonly bool $textToRender,
        public readonly bool $masks,
        public readonly bool $editReferences,
        public readonly int $maxReferences,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            textToRender: (bool) ($d['text_to_render'] ?? false),
            masks: (bool) ($d['masks'] ?? false),
            editReferences: (bool) ($d['edit_references'] ?? false),
            maxReferences: (int) ($d['max_references'] ?? 0),
        );
    }
}
