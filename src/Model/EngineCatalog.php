<?php

declare(strict_types=1);

namespace Mnml\Model;

/** The engines and video models, with their prices and capabilities. */
final class EngineCatalog
{
    public function __construct(
        /** @var list<Engine> */
        public readonly array $engines,
        /** @var list<VideoModel> */
        public readonly array $videoModels,
    ) {
    }

    /** @param array<string, mixed> $d The API's answer. */
    public static function fromArray(array $d): self
    {
        return new self(
            engines: array_values(array_map([Engine::class, 'fromArray'], $d['engines'] ?? [])),
            videoModels: array_values(array_map([VideoModel::class, 'fromArray'], $d['video_models'] ?? [])),
        );
    }
}
