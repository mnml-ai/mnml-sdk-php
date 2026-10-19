<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\EngineCatalog;

/** The engines and video models, with their prices and capabilities. */
final class Engines
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(): EngineCatalog
    {
        return EngineCatalog::fromArray($this->client->request('GET', '/v2/engines'));
    }
}
