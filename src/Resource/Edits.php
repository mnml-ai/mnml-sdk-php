<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\Job;
use Mnml\Model\JobStarted;

/** Prompt edits and erasing, over the whole image or a region. */
final class Edits
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param array<string, mixed> $request kind (edit or erase), prompt, image, job_id, engine, mode, references, region, mask, webhook_url. */
    public function create(array $request, ?string $idempotencyKey = null): JobStarted
    {
        return JobStarted::fromArray($this->client->request('POST', '/v2/edits', $request, $idempotencyKey));
    }

    /**
     * Starts the job and waits for it.
     *
     * @param array<string, mixed> $request
     * @param array{interval?: float, timeout?: float} $wait Seconds.
     */
    public function createAndWait(array $request, array $wait = [], ?string $idempotencyKey = null): Job
    {
        return $this->client->jobs->wait($this->create($request, $idempotencyKey)->id, $wait);
    }
}
