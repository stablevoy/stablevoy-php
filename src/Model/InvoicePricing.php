<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * The price of an invoice created in another currency than USD, and the rate that turned it into amountUsdCents. Fixed
 * when the invoice was created. $amount is in the smallest unit of $currency: 4999 with exponent 2 is 49.99 EUR.
 */
final class InvoicePricing
{
    public function __construct(
        /** ISO 4217 code, one of Currencies::list(). */
        public readonly string $currency,
        public readonly int $amount,
        /** Decimal places of the currency: 2 for EUR, 0 for JPY. */
        public readonly int $exponent,
        /** US dollars for one unit of the currency, a decimal string such as "1.0992". */
        public readonly string $usdPerUnit,
        /** 'ecb' (ECB reference rate, checked against Coinbase) or 'merchant' (the store's own fixed rate). */
        public readonly string $rateSource,
        /** When the rate was valid: the ECB publication time, or when the merchant set the store rate. */
        public readonly \DateTimeImmutable $rateAt,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('currency'), $f->int('amount'), $f->int('exponent'), $f->string('usdPerUnit'), $f->string('rateSource'), $f->date('rateAt'));
    }
}
