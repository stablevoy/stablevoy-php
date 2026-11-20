<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Http\Transport;
use Stablevoy\Model\Currency;
use Stablevoy\Model\Fields;

/** Currencies an invoice can be priced in, and their exchange rates. Read only: a store's own rate is set in the dashboard. */
final class Currencies
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly Transport $transport) {}

    /**
     * Every supported currency with the market rate in use (ECB reference rates, checked against Coinbase).
     *
     * @return list<Currency>
     */
    public function list(): array
    {
        return self::parse($this->transport->get('/v1/currencies'));
    }

    /**
     * The same list for one store (the key's own store), with its own fixed rates and the rate an invoice of the store
     * would use now. effective null: an invoice in that currency is refused right now.
     *
     * @return list<Currency>
     */
    public function forStore(string $storeId): array
    {
        // Ids are UUIDs; anything else (such as "..") could make the request reach another path with your key.
        if (preg_match(self::UUID, $storeId) !== 1) {
            throw new \InvalidArgumentException('A store id is a UUID, like 7c3360b5-e8cc-4775-8a13-7324d4f1296e');
        }
        return self::parse($this->transport->get("/v1/stores/$storeId/currencies"));
    }

    /**
     * @param array<mixed> $data
     * @return list<Currency>
     */
    private static function parse(array $data): array
    {
        if (!array_is_list($data)) {
            throw new UnexpectedResponseException('currencies is not a list');
        }
        return array_map(static fn(mixed $item, int $i): Currency => Currency::fromFields(Fields::of($item, "currencies[$i]")), $data, array_keys($data));
    }
}
