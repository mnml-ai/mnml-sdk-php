<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\Job;
use Mnml\Model\JobStarted;

/** Video from a still image. A clip takes minutes. */
final class Videos
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param array<string, mixed> $request image, job_id, model, duration_seconds, camera_movement, motion, prompt, end_frame, cinematic, webhook_url. */
    public function create(array $request, ?string $idempotencyKey = null): JobStarted
    {
        return JobStarted::fromArray($this->client->request('POST', '/v2/videos', $request, $idempotencyKey));
    }

    /**
     * Starts the job and waits for it. Reads every 10 s, for up to 20 minutes, unless $wait says otherwise.
     *
     * @param array<string, mixed> $request
     * @param array{interval?: float, timeout?: float} $wait Seconds.
     */
    public function createAndWait(array $request, array $wait = [], ?string $idempotencyKey = null): Job
    {
        return $this->client->jobs->wait($this->create($request, $idempotencyKey)->id, $wait + ['interval' => 10.0, 'timeout' => 1200.0]);
    }
}
