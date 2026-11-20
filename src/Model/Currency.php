<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** One currency an invoice can be priced in, with its rates (GET /v1/currencies, GET /v1/stores/{id}/currencies). */
final class Currency
{
    public function __construct(
        /** ISO 4217 code. */
        public readonly string $code,
        /** Decimal places of the smallest unit: 2 for EUR (amount 1999 is 19.99), 0 for JPY. */
        public readonly int $exponent,
        /** Null for USD and before the first refresh. */
        public readonly ?MarketRate $market,
        /** The store's own rate (store list only); null when the store uses the market rate. */
        public readonly ?StoreRate $store,
        /** The rate an invoice created now would use; null: an invoice in this currency is refused right now. */
        public readonly ?CurrencyRate $effective,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $market = $f->nullableObject('market');
        $store = $f->nullableObject('store');
        $effective = $f->nullableObject('effective');
        return new self(
            $f->string('code'),
            $f->int('exponent'),
            $market === null ? null : MarketRate::fromFields($market),
            $store === null ? null : StoreRate::fromFields($store),
            $effective === null ? null : CurrencyRate::fromFields($effective),
        );
    }
}
