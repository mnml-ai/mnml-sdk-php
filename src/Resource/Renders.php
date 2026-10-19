<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\Job;
use Mnml\Model\RenderStarted;

/** Renders from a source image or a prompt alone. count starts several jobs at once. */
final class Renders
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Starts a render. With $wait (1–90 seconds) the API holds the answer while the render runs,
     * and RenderStarted::$jobs carries the outputs, with no polling.
     *
     * @param array<string, mixed> $request prompt (required), engine, mode, image, job_id,
     *   references, settings, aspect_ratio, count, seed, webhook_url.
     */
    public function create(array $request, ?int $wait = null, ?string $idempotencyKey = null): RenderStarted
    {
        $path = Client::waitPath('/v2/renders', $wait);

        return RenderStarted::fromArray($this->client->request('POST', $path, $request, $idempotencyKey, $wait ?? 0));
    }

    /**
     * Starts the render and waits for every job it started (one per count). The API holds the
     * first answer while it runs, so most renders come back without a single poll.
     *
     * @param array<string, mixed> $request
     * @param array{interval?: float, timeout?: float} $wait Seconds.
     * @return list<Job>
     */
    public function createAndWait(array $request, array $wait = [], ?string $idempotencyKey = null): array
    {
        $started = $this->create($request, Client::MAX_WAIT_SECONDS, $idempotencyKey);
        $jobs = $started->jobs ?? [];
        $settled = array_filter($jobs, static fn (Job $j) => $j->isSettled());
        if (count($jobs) === count($started->ids) && count($settled) === count($jobs)) {
            return $jobs;
        }

        return $this->client->jobs->waitAll($started->ids, $wait);
    }
}
