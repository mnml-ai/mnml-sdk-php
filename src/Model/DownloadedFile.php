<?php

declare(strict_types=1);

namespace Mnml\Model;

/** A job output's bytes. */
final class DownloadedFile
{
    public function __construct(
        public readonly string $data,
        /** image/jpeg, image/png, image/webp or video/mp4. */
        public readonly ?string $contentType,
    ) {
    }
}
