<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Http\Transport;
use Stablevoy\Model\Fields;
use Stablevoy\Model\Limits;
use Stablevoy\Model\Network;
use Stablevoy\Model\Pricing;

/** Public facts about the platform: invoice limits, the price list and the supported networks. */
final class Platform
{
    public function __construct(private readonly Transport $transport) {}

    public function limits(): Limits
    {
        return Limits::fromFields(Fields::of($this->transport->get('/v1/limits'), 'limits'));
    }

    public function pricing(): Pricing
    {
        return Pricing::fromFields(Fields::of($this->transport->get('/v1/pricing'), 'pricing'));
    }

    /**
     * @return list<Network>
     */
    public function networks(): array
    {
        $data = $this->transport->get('/v1/networks');
        if (!array_is_list($data)) {
            throw new UnexpectedResponseException('networks is not a list');
        }
        return array_map(static fn(mixed $item, int $i): Network => Network::fromFields(Fields::of($item, "networks[$i]")), $data, array_keys($data));
    }
}
