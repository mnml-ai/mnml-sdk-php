<?php

declare(strict_types=1);

namespace Mnml\Exception;

/** A webhook delivery whose signature or timestamp does not hold. */
final class WebhookVerificationException extends \RuntimeException
{
}
