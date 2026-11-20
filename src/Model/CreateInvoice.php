<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * What to create an invoice for: the amount, your order id and where the buyer returns after paying. Give the amount
 * either in US cents ($amountUsdCents), or in your own currency with CreateInvoice::inCurrency() ($amount in its smallest
 * unit plus $currency, see Currencies::list()): the API converts it once, at creation, and keeps the rate in Invoice::$pricing.
 */
final class CreateInvoice
{
    public function __construct(
        public readonly ?int $amountUsdCents,
        public readonly ?string $externalReference = null,
        public readonly ?string $returnUrl = null,
        /**
         * How long the buyer has to pay, 15 to 10080 minutes (7 days). Null: the store's payment window, else the
         * platform default (Platform::limits() tells it). Use a day or more for an invoice sent by email. The bounds are
         * checked by the API, which answers invalid_invoice_lifetime.
         */
        public readonly ?int $expiresInMinutes = null,
        /** In the smallest unit of $currency: 4999 is 49.99 EUR, 1500 is 1500 JPY. Goes with $currency, instead of $amountUsdCents. */
        public readonly ?int $amount = null,
        /** ISO 4217 code such as 'EUR'. Goes with $amount. */
        public readonly ?string $currency = null,
    ) {
        if ($amountUsdCents !== null) {
            if ($amount !== null || $currency !== null) {
                throw new \InvalidArgumentException('Give amountUsdCents, or amount with currency, not both');
            }
            if ($amountUsdCents < 1) {
                throw new \InvalidArgumentException('amountUsdCents must be a positive number of US cents, for example 1999 for $19.99');
            }
            return;
        }
        if ($amount === null || $currency === null) {
            throw new \InvalidArgumentException('Give amountUsdCents, or amount with currency');
        }
        if ($amount < 1) {
            throw new \InvalidArgumentException('amount must be a positive number of the smallest unit of the currency, for example 4999 for 49.99 EUR');
        }
        // The list of currencies is the API's (Currencies::list()); here only the shape of an ISO 4217 code.
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('currency must be an ISO 4217 code in capitals, such as EUR');
        }
    }

    /** A price in your own currency: $amount in its smallest unit (4999 is 49.99 EUR) and the ISO 4217 $currency. */
    public static function inCurrency(int $amount, string $currency, ?string $externalReference = null, ?string $returnUrl = null, ?int $expiresInMinutes = null): self
    {
        return new self(null, $externalReference, $returnUrl, $expiresInMinutes, $amount, $currency);
    }

    /**
     * @return array{amountUsdCents?: int, amount?: int, currency?: string, externalReference?: string, returnUrl?: string, expiresInMinutes?: int}
     */
    public function toArray(): array
    {
        $body = $this->amountUsdCents !== null ? ['amountUsdCents' => $this->amountUsdCents] : ['amount' => (int) $this->amount, 'currency' => (string) $this->currency];
        if ($this->externalReference !== null) {
            $body['externalReference'] = $this->externalReference;
        }
        if ($this->returnUrl !== null) {
            $body['returnUrl'] = $this->returnUrl;
        }
        if ($this->expiresInMinutes !== null) {
            $body['expiresInMinutes'] = $this->expiresInMinutes;
        }
        return $body;
    }
}
