# Changelog

All notable changes to this package are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## 0.2.0 — unreleased

Amounts in US dollars. API v2 now answers every amount in dollars beside the credits; the SDK
reads the dollar fields and steers you to them. Nothing is removed, so 0.1.0 code still runs.

- New properties, each a float of US dollars to four decimals: `chargedUsd` on `RenderStarted`
  and `JobStarted`; `chargedUsd` and `refundedUsd` on `Job`; `refundedUsd` on `JobCanceled`;
  `balanceUsd` on `Account`; `dailyLimitUsd` on `AccountKey` and `dailyUsd` on `AccountLimits`
  (null for no limit); `renderUsd` and `editUsd` on `EnginePrices`; `usd` on `VideoPrice`;
  `balanceUsd` and `thresholdUsd` on the `credits.low` webhook's `CreditsLow`. They are the last
  constructor parameters, with defaults, so a model built by name or position still builds.
- Deprecated (`@deprecated`, removed in a later release): `creditsCharged`, `creditsRefunded`,
  `Account::$credits` (`AccountCredits`), `dailyCreditLimit`, `dailyCredits`,
  `EnginePrices::$render` and `$edit`, `VideoPrice::$credits`, and `CreditsLow::$balance` and
  `$threshold`. Each names the dollar property to read instead.
- `spec/openapi.json` refreshed (Nov 9 2026). The coverage tests now also check that the SDK
  deprecates exactly the fields the spec does.
- README reads the dollar properties.

## 0.1.0

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
