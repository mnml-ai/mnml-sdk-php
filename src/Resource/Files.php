<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\DownloadedFile;
use Mnml\Model\JobOutput;

/** A job's outputs. Their links are signed and expire, so download what you keep. */
final class Files
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * The file behind an output (or its url). The signature in the link is the credential, so
     * your key is not sent with it. An expired link throws MnmlException with NOT_FOUND: read the
     * job again for a fresh one.
     */
    public function download(JobOutput|string $output): DownloadedFile
    {
        $res = $this->client->download($output instanceof JobOutput ? $output->url : $output);

        return new DownloadedFile($res['body'], $res['headers']['content-type'] ?? null);
    }
}
