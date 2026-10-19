<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Exception\MnmlException;
use Mnml\Exception\TimeoutException;
use Mnml\Model\Job;
use Mnml\Model\JobCanceled;
use Mnml\Model\JobError;
use Mnml\Model\JobStreamEvent;

/** Read, wait for, follow and cancel jobs. */
final class Jobs
{
    public function __construct(private readonly Client $client)
    {
    }

    /** Reads a job. With $wait (1–90 seconds) the API holds the read and answers as soon as the job settles. */
    public function get(string $id, ?int $wait = null): Job
    {
        $path = Client::waitPath('/v2/jobs/' . rawurlencode($id), $wait);

        return Job::fromArray($this->client->request('GET', $path, null, null, $wait ?? 0));
    }

    /** Cancels a job; one cancelled before it produced anything is refunded. */
    public function cancel(string $id, ?string $idempotencyKey = null): JobCanceled
    {
        return JobCanceled::fromArray($this->client->request('POST', '/v2/jobs/' . rawurlencode($id) . '/cancel', null, $idempotencyKey));
    }

    /**
     * Reads the job until it succeeds, fails or is cancelled, and returns it. Each read asks the
     * API to hold it until the job settles, so a render usually takes one read. Throws
     * TimeoutException when the time is up; the job keeps running.
     *
     * @param array{interval?: float, timeout?: float} $options Seconds: the least time between two
     *   reads (default 3) and when to give up (default 600).
     */
    public function wait(string $id, array $options = []): Job
    {
        $interval = (float) ($options['interval'] ?? 3.0);
        $deadline = microtime(true) + (float) ($options['timeout'] ?? 600.0);
        while (true) {
            $left = (int) floor($deadline - microtime(true));
            $began = microtime(true);
            $job = $this->get($id, $left >= 1 ? min(Client::MAX_WAIT_SECONDS, $left) : null);
            if ($job->isSettled()) {
                return $job;
            }
            if (microtime(true) + $interval > $deadline) {
                throw new TimeoutException($id);
            }
            // Answered early, still running: space the next read.
            $pause = $interval - (microtime(true) - $began);
            if ($pause > 0) {
                $this->client->pause($pause);
            }
        }
    }

    /**
     * wait() for several jobs, such as every id a render with count started, in the order given.
     *
     * @param list<string> $ids
     * @param array{interval?: float, timeout?: float} $options
     * @return list<Job>
     */
    public function waitAll(array $ids, array $options = []): array
    {
        // One after another: each read is held until its job settles, so the total is about the slowest job.
        return array_map(fn (string $id) => $this->wait($id, $options), array_values($ids));
    }

    /**
     * Follows a job as it runs, over one connection (Server-Sent Events): each change of the job,
     * then one last event (done, timeout or error), after which the generator ends. Stopping the
     * loop hangs up; the job keeps running.
     *
     *     foreach ($mnml->jobs->stream($id) as $event) {
     *         if ($event->type === 'done') echo $event->job->outputs[0]->url;
     *     }
     *
     * @return \Generator<int, JobStreamEvent>
     */
    public function stream(string $id): \Generator
    {
        $res = $this->client->openStream('/v2/jobs/' . rawurlencode($id) . '/events');
        $block = [];
        foreach ($res['lines'] as $line) {
            if ($line !== '') {
                $block[] = $line;
                continue;
            }
            $event = self::parse($block);
            $block = [];
            if ($event === null) {
                continue;
            }
            yield $event;
            if ($event->isFinal()) {
                return;
            }
        }
        throw new MnmlException(
            'STREAM_ENDED',
            'The job stream ended before the job settled. Read the job, or open the stream again.',
            $res['status'],
            $res['headers']['x-request-id'] ?? null,
        );
    }

    /**
     * One Server-Sent Events block as an event; null for a comment (": ping") or an unknown event.
     *
     * @param list<string> $lines
     */
    private static function parse(array $lines): ?JobStreamEvent
    {
        $type = 'message';
        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, ':')) {
                continue;
            }
            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = str_starts_with($value, ' ') ? substr($value, 1) : $value;
            if ($field === 'event') {
                $type = $value;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }
        if ($data === []) {
            return null;
        }
        $payload = json_decode(implode("\n", $data), true, 512, JSON_THROW_ON_ERROR);

        return match ($type) {
            'job', 'done', 'timeout' => new JobStreamEvent($type, Job::fromArray($payload)),
            'error' => new JobStreamEvent($type, null, JobError::fromArray($payload)),
            default => null,
        };
    }
}
