<?php

declare(strict_types=1);

namespace Mnml;

/** No answer came: a dropped connection or a timeout. The client retries it; the last one is thrown. */
final class TransportException extends \RuntimeException
{
}
