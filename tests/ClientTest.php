<?php

declare(strict_types=1);

namespace Mnml\Tests;

use Mnml\Client;
use Mnml\Exception\MnmlException;
use Mnml\Exception\TimeoutException;
use Mnml\Image;
use Mnml\JobStatus;
use Mnml\Mode;
use Mnml\Model\JobOutput;
use Mnml\ReferenceMode;
use Mnml\TransportException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\x01\x02\x03";

    public function testARenderPostsItsBodyWithTheKeyAnIdempotencyKeyAndADataUri(): void
    {
        $api = (new FakeTransport())->ok('{"id":"42","ids":["42"],"status":"queued","charged_usd":0.297,"credits_charged":30,"replayed":false,"notes":[]}');

        $started = $api->client()->renders->create([
            'prompt' => 'Timber facade, dusk',
            'mode' => Mode::EXTERIOR,
            'image' => Image::fromBytes(self::PNG),
            'references' => [Image::reference('https://x.test/ref.jpg', ReferenceMode::MATERIAL)],
        ]);

        self::assertSame('42', $started->id);
        self::assertSame(JobStatus::QUEUED, $started->status);
        self::assertSame(0.297, $started->chargedUsd);
        $r = $api->requests[0];
        self::assertSame(['POST', 'https://api.test/v2/renders'], [$r['method'], $r['url']]);
        self::assertSame('Bearer test-key', $r['headers']['authorization']);
        self::assertStringStartsWith('mnml-sdk-php/', $r['headers']['user-agent']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $r['headers']['idempotency-key']);
        $body = json_decode((string) $r['body'], true);
        self::assertStringStartsWith('data:image/png;base64,', $body['image']);
        self::assertSame(['image' => 'https://x.test/ref.jpg', 'mode' => 'material'], $body['references'][0]);
    }

    public function testARetryAfterA503SendsTheSameIdempotencyKeyAfterRetryAfter(): void
    {
        $api = (new FakeTransport())
            ->fail(503, 'SERVICE_UNAVAILABLE', 'Busy.', ['Retry-After' => '5'])
            ->ok('{"id":"7","status":"queued","charged_usd":0.0198,"credits_charged":2,"replayed":false,"notes":[]}');

        $api->client()->edits->create(['prompt' => 'Add trees', 'job_id' => '41']);

        self::assertCount(2, $api->requests);
        self::assertSame($api->requests[0]['headers']['idempotency-key'], $api->requests[1]['headers']['idempotency-key']);
        self::assertSame([5.0], $api->pauses);
    }

    public function testADroppedConnectionIsRetriedWithABackoff(): void
    {
        $api = (new FakeTransport())->drop()->ok(FakeTransport::JOB);

        $job = $api->client()->jobs->get('42');

        self::assertSame('42', $job->id);
        self::assertCount(1, $api->pauses);
        self::assertGreaterThanOrEqual(0.5, $api->pauses[0]);
    }

    public function testTheLastDroppedConnectionIsThrown(): void
    {
        $api = (new FakeTransport())->drop();

        $this->expectException(TransportException::class);
        $api->client(maxRetries: 0)->account->get();
    }

    public function testALongRetryAfterIsTheCallersToHandle(): void
    {
        $api = (new FakeTransport())->fail(429, 'RATE_LIMITED', 'Daily limit reached.', ['Retry-After' => '3600']);

        try {
            $api->client()->account->get();
            self::fail('no exception');
        } catch (MnmlException $e) {
            self::assertSame('RATE_LIMITED', $e->errorCode);
            self::assertSame(429, $e->status);
            self::assertSame('req_test', $e->requestId);
        }
        self::assertCount(1, $api->requests);
        self::assertSame([], $api->pauses);
    }

    public function testARefusalCarriesTheApisCodeMessageAndIssues(): void
    {
        $api = (new FakeTransport())->answer(400, '{"success":false,"error":{"code":"VALIDATION_FAILED","message":"Check the fields.",'
            . '"issues":[{"path":"prompt","message":"Required."}],"details":{"reason":"x"}}}');

        try {
            $api->client()->renders->create([]);
            self::fail('no exception');
        } catch (MnmlException $e) {
            self::assertSame('VALIDATION_FAILED', $e->errorCode);
            self::assertSame('Check the fields.', $e->getMessage());
            self::assertSame('prompt', $e->issues[0]->path);
            self::assertSame(['reason' => 'x'], $e->details);
        }
    }

    public function testAnAnswerThatIsNotTheEnvelopeIsAnHttpError(): void
    {
        $api = (new FakeTransport())->answer(502, '<html>Bad gateway</html>');

        $this->expectExceptionObject(new MnmlException('HTTP_ERROR', 'The API answered 502.', 502));
        $api->client(maxRetries: 0)->engines->list();
    }

    public function testWaitHoldsEachReadAndReturnsTheSettledJob(): void
    {
        $api = (new FakeTransport())->ok(FakeTransport::jobWith('processing'))->ok(FakeTransport::JOB);

        $job = $api->client()->jobs->wait('42');

        self::assertTrue($job->isSettled());
        self::assertSame('https://api.test/v2/files/42?exp=1&sig=x', $job->outputs[0]->url);
        foreach ($api->requests as $r) {
            self::assertStringStartsWith('https://api.test/v2/jobs/42?wait=', $r['url']);
        }
    }

    public function testWaitGivesUpAfterItsTimeout(): void
    {
        $api = (new FakeTransport())->ok(FakeTransport::jobWith('processing'));

        $this->expectException(TimeoutException::class);
        $api->client()->jobs->wait('42', ['timeout' => 0.01]);
    }

    public function testARenderThatSettledInTheHeldAnswerNeedsNoPoll(): void
    {
        $api = (new FakeTransport())->ok('{"id":"42","ids":["42"],"status":"succeeded","charged_usd":0.297,"credits_charged":30,"replayed":false,"notes":[],"jobs":[' . FakeTransport::JOB . ']}');

        $jobs = $api->client()->renders->createAndWait(['prompt' => 'A', 'mode' => Mode::TEXT_TO_RENDER]);

        self::assertCount(1, $jobs);
        self::assertSame(0.297, $jobs[0]->chargedUsd);
        self::assertSame(0.0, $jobs[0]->refundedUsd);
        self::assertSame('https://api.test/v2/renders?wait=90', $api->requests[0]['url']);
        self::assertCount(1, $api->requests);
    }

    public function testAWaitOutside1To90IsRefusedBeforeAnyCall(): void
    {
        $api = new FakeTransport();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $api->client()->jobs->get('42', 91);
        } finally {
            self::assertSame([], $api->requests);
        }
    }

    public function testCancelPostsWithAnIdempotencyKey(): void
    {
        $api = (new FakeTransport())->ok('{"id":"42","outcome":"refunded","refunded_usd":0.297,"credits_refunded":30}');

        $canceled = $api->client()->jobs->cancel('42');

        self::assertSame('refunded', $canceled->outcome);
        self::assertSame(0.297, $canceled->refundedUsd);
        self::assertSame('https://api.test/v2/jobs/42/cancel', $api->requests[0]['url']);
        self::assertArrayHasKey('idempotency-key', $api->requests[0]['headers']);
    }

    public function testADownloadSendsNoKey(): void
    {
        $api = (new FakeTransport())->answer(200, 'JPEGBYTES', ['Content-Type' => 'image/jpeg']);

        $file = $api->client()->files->download(new JobOutput('https://api.test/v2/files/42?exp=1&sig=x', 'image', '2026-10-18T10:00:00Z'));

        self::assertSame('JPEGBYTES', $file->data);
        self::assertSame('image/jpeg', $file->contentType);
        self::assertArrayNotHasKey('authorization', $api->requests[0]['headers']);
    }

    public function testAStreamYieldsEachChangeThenTheLastEvent(): void
    {
        $sse = ": ping\n\nevent: job\ndata: " . FakeTransport::jobWith('processing') . "\n\nevent: done\ndata: " . FakeTransport::JOB
            . "\n\nevent: job\ndata: {\"never\":1}\n\n";
        $api = (new FakeTransport())->answer(200, $sse, ['Content-Type' => 'text/event-stream']);

        $types = [];
        foreach ($api->client()->jobs->stream('42') as $event) {
            $types[] = $event->type;
        }

        self::assertSame(['job', 'done'], $types);
        self::assertSame('text/event-stream', $api->requests[0]['headers']['accept']);
    }

    public function testAStreamThatEndsEarlySaysSo(): void
    {
        $api = (new FakeTransport())->answer(200, "event: job\ndata: " . FakeTransport::jobWith('processing') . "\n\n");

        $this->expectExceptionObject(new MnmlException('STREAM_ENDED', 'The job stream ended before the job settled. Read the job, or open the stream again.', 200));
        foreach ($api->client()->jobs->stream('42') as $_) {
        }
    }

    public function testImagesAreLabelledByTheirFirstBytes(): void
    {
        self::assertStringStartsWith('data:image/png;base64,', Image::fromBytes(self::PNG));
        self::assertStringStartsWith('data:image/jpeg;base64,', Image::fromBytes("\xFF\xD8\xFF\x00"));
    }

    public function testAClientNeedsAKey(): void
    {
        $before = getenv('MNML_API_KEY');
        putenv('MNML_API_KEY');
        try {
            $this->expectException(\InvalidArgumentException::class);
            new Client();
        } finally {
            if ($before !== false) {
                putenv("MNML_API_KEY={$before}");
            }
        }
    }
}
