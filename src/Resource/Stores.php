<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Http\Transport;
use Stablevoy\Model\Fields;
use Stablevoy\Model\Store;

final class Stores
{
    public function __construct(private readonly Transport $transport) {}

    /** The store the API key belongs to: a cheap way to check that the key works. */
    public function current(): Store
    {
        return Store::fromFields(Fields::of($this->transport->get('/v1/store'), 'store'));
    }
}
