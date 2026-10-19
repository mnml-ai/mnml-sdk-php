<?php

declare(strict_types=1);

namespace Mnml\Resource;

use Mnml\Client;
use Mnml\Model\Account as AccountModel;

/** The key's account: balance, tier and limits. */
final class Account
{
    public function __construct(private readonly Client $client)
    {
    }

    public function get(): AccountModel
    {
        return AccountModel::fromArray($this->client->request('GET', '/v2/account'));
    }
}
