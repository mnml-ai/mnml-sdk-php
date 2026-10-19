<?php

declare(strict_types=1);

namespace Mnml\Exception;

use Mnml\Model\Issue;

/**
 * An answer that was not a success: the API's own code, message and request id. The codes are
 * listed at https://developers.mnml.ai/docs/errors.
 *
 *     catch (MnmlException $e) { if ($e->errorCode === 'INSUFFICIENT_CREDITS') { … } }
 */
class MnmlException extends \RuntimeException
{
    /**
     * @param string $errorCode The API's error code, such as INSUFFICIENT_CREDITS or RATE_LIMITED.
     * @param int $status The HTTP status (also getCode()).
     * @param ?string $requestId The X-Request-Id to quote to support.
     * @param array<string, mixed>|null $details More about the refusal (details.reason on a 429, say).
     * @param list<Issue> $issues On VALIDATION_FAILED, each field that was refused.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?string $requestId = null,
        public readonly ?array $details = null,
        public readonly array $issues = [],
    ) {
        parent::__construct($message, $status);
    }
}
