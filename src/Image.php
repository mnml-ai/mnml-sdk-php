<?php

declare(strict_types=1);

namespace Mnml;

/**
 * Images for a request, up to 15 MB. A public https:// link, a data:image/…;base64, URI or base64
 * text goes as it is; these helpers turn a file or bytes into a data URI. The API checks the image
 * again, so the type read off the first bytes is only a label.
 *
 *     'image' => Image::fromFile('massing.png'),
 *     'image' => 'https://example.com/massing.png',
 */
final class Image
{
    public static function fromFile(string $path): string
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Cannot read the image at {$path}.");
        }

        return self::fromBytes($bytes);
    }

    public static function fromBytes(string $bytes): string
    {
        if ($bytes === '') {
            throw new \InvalidArgumentException('An image cannot be empty.');
        }

        return 'data:' . self::sniff($bytes) . ';base64,' . base64_encode($bytes);
    }

    /** A reference image and what to take from it (one of {@see ReferenceMode}). */
    public static function reference(string $image, ?string $mode = null): array
    {
        return array_filter(['image' => $image, 'mode' => $mode], static fn ($v) => $v !== null);
    }

    public static function sniff(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG") => 'image/png',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
