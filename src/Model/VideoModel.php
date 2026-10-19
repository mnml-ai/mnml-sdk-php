<?php

declare(strict_types=1);

namespace Mnml\Model;

/** A video model. */
final class VideoModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $default,
        public readonly bool $available,
        /** @var list<VideoPrice> */
        public readonly array $prices,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            default: (bool) ($d['default'] ?? false),
            available: (bool) ($d['available'] ?? false),
            prices: array_values(array_map([VideoPrice::class, 'fromArray'], $d['prices'] ?? [])),
        );
    }
}
