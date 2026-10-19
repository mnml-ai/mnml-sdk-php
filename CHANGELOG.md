# Changelog

All notable changes to this package are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## 0.1.0 — unreleased

First release.

- Every endpoint of API v2: `renders`, `edits`, `enhancements`, `videos`, `jobs` (get, cancel,
  wait, waitAll, stream), `files->download`, `account`, `engines`.
- `createAndWait` on every create: a render waits in the held answer (`wait=90`) and reads on
  only for what is still running.
- `jobs->stream()` as a generator, for a `foreach` loop.
- Retries for a 429, a 5xx or a dropped connection, after `Retry-After` (up to 60 s) or a
  backoff, with one idempotency key per call kept across them.
- `Webhooks::verify` and `Webhooks::sign` (Standard Webhooks, HMAC-SHA256).
- No dependencies: PHP 8.1 or newer with its own HTTPS streams; a `Transport` of your own
  (Guzzle, a proxy) plugs in. Answers are typed, readonly models; the tests check them against
  the API's OpenAPI document.
