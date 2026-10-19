# mnml PHP SDK

The official PHP client for the [mnml API](https://developers.mnml.ai): architecture renders,
edits, enhancements and video, from your own site, plugin or app.

- Every endpoint of API v2: renders, edits, enhancements, video, jobs, account, engines
- Typed, readonly answers, checked against the API's OpenAPI document
- Retries, timeouts and idempotency keys handled for you
- `createAndWait()` and `jobs->wait()` to wait for a job, `jobs->stream()` to follow one in a
  `foreach`, `files->download()` to keep its output
- Webhook signature verification
- No dependencies: PHP 8.1 or newer. Works in WordPress plugins, Laravel and shared hosting

```bash
composer require mnml-ai/sdk
```

## Quick start

Create an API key in the [console](https://developers.mnml.ai/console/keys), then:

```php
use Mnml\Client;
use Mnml\Image;
use Mnml\Mode;

$mnml = new Client(); // reads MNML_API_KEY

$jobs = $mnml->renders->createAndWait([
    'mode' => Mode::EXTERIOR,
    'image' => Image::fromFile('massing.png'), // or 'https://example.com/massing.png'
    'prompt' => 'Timber facade, late afternoon light, olive trees',
]);
echo $jobs[0]->status, ' ', $jobs[0]->outputs[0]->url;
```

Every call spends credits from your prepaid API balance. Top it up in dollars on
[Billing](https://developers.mnml.ai/console/billing).

## Configuration

```php
$mnml = new Client([
    'api_key' => getenv('MNML_API_KEY'), // the default
    'max_retries' => 2,                  // retries for a 429, a 5xx or a dropped connection
    'timeout' => 60,                     // seconds per request
    'transport' => $myTransport,         // optional: your own Mnml\Transport (Guzzle, a proxy)
]);
```

Keep your key on the server; never print it into a page.

## Methods

| Method                                | Endpoint                    | What it does                                        |
| ------------------------------------- | --------------------------- | --------------------------------------------------- |
| `renders->create($request)`           | `POST /v2/renders`          | Render from an image, or from a prompt alone        |
| `edits->create($request)`             | `POST /v2/edits`            | Edit or erase, over the whole image or a region     |
| `enhancements->create($request)`      | `POST /v2/enhancements`     | Upscale, enhance, remove the background, outpaint   |
| `videos->create($request)`            | `POST /v2/videos`           | Video from a still                                  |
| `jobs->get($id)`                      | `GET /v2/jobs/{id}`         | Read a job                                          |
| `jobs->cancel($id)`                   | `POST /v2/jobs/{id}/cancel` | Cancel a job (refunded if it had not produced yet)  |
| `account->get()`                      | `GET /v2/account`           | Balance, tier and limits for this key               |
| `engines->list()`                     | `GET /v2/engines`           | Engines, video models, prices and capabilities      |
| `files->download($output)`            | `GET /v2/files/{id}`        | A job output's bytes, from its signed link          |
| `jobs->wait($id, $options)`           | `GET /v2/jobs/{id}`         | Read a job until it succeeds, fails or is cancelled |
| `jobs->waitAll($ids, $options)`       | `GET /v2/jobs/{id}`         | `wait` for several jobs                             |
| `jobs->stream($id)`                   | `GET /v2/jobs/{id}/events`  | Follow a job as it changes, over one connection     |
| `<resource>->createAndWait($request)` | the create, then the job    | Start a job and wait for it (a render: every job)   |

Requests are arrays with the API's own field names (`aspect_ratio`, `job_id` …); the
[API reference](https://developers.mnml.ai/docs) lists them. Every create takes an optional
`$idempotencyKey`. The client makes one per call when you pass none and keeps it across its own
retries, so a retried call is never charged twice. `Mode`, `AspectRatio`, `ReferenceMode`,
`EnhancementKind` and `JobStatus` hold the values the API takes.

### Edits and regions

```php
$job = $mnml->edits->createAndWait([
    'job_id' => $render->id,
    'prompt' => 'Replace the lawn with a reflecting pool',
    'region' => ['box' => ['x' => 0, 'y' => 0.6, 'width' => 1, 'height' => 0.4]],
]);
```

### Following a job

```php
foreach ($mnml->jobs->stream($started->id) as $event) {
    if ($event->type === 'done') {
        echo $event->job->outputs[0]->url;
    }
}
```

### Downloading outputs

Output links are signed and expire within hours. Keep what you need:

```php
$file = $mnml->files->download($job->outputs[0]);
file_put_contents('render.jpg', $file->data);
```

## Errors

A refusal throws `Mnml\Exception\MnmlException` with the API's `errorCode`
(`INSUFFICIENT_CREDITS`, `VALIDATION_FAILED`, `RATE_LIMITED` …), the HTTP `status`, the
`requestId` to quote to support, and, on `VALIDATION_FAILED`, each refused field in `issues`.
`jobs->wait()` throws `Mnml\Exception\TimeoutException` when its time is up; the job keeps
running. A connection that never answered throws `Mnml\TransportException`. The codes:
[developers.mnml.ai/docs/errors](https://developers.mnml.ai/docs/errors).

```php
try {
    $mnml->renders->create($request);
} catch (Mnml\Exception\MnmlException $e) {
    if ($e->errorCode === 'INSUFFICIENT_CREDITS') { /* top up */ }
}
```

## Webhooks

Verify each delivery against your endpoint's `whsec_` secret, with the raw body:

```php
$event = Mnml\Webhooks::verify(file_get_contents('php://input'), getallheaders(), $secret);
if ($event->type === 'job.succeeded') {
    echo $event->job()->outputs[0]->url;
}
```

In Laravel, pass `$request->getContent()` and `$request->headers->all()`.

## Versioning

This package follows [Semantic Versioning](https://semver.org/). The API is versioned in its path
(`/v2`); a release never changes which API version it calls without a major version. See
[CHANGELOG.md](./CHANGELOG.md).

## License

MIT
