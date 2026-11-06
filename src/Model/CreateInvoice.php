<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** What to create an invoice for: the amount in US cents, your order id and where the buyer returns after paying. */
final class CreateInvoice
{
    public function __construct(
        public readonly int $amountUsdCents,
        public readonly ?string $externalReference = null,
        public readonly ?string $returnUrl = null,
        /**
         * How long the buyer has to pay, 15 to 10080 minutes (7 days). Null: the store's payment window, else the
         * platform default (Platform::limits() tells it). Use a day or more for an invoice sent by email. The bounds are
         * checked by the API, which answers invalid_invoice_lifetime.
         */
        public readonly ?int $expiresInMinutes = null,
    ) {
        if ($amountUsdCents < 1) {
            throw new \InvalidArgumentException('amountUsdCents must be a positive number of US cents, for example 1999 for $19.99');
        }
    }

    /**
     * @return array{amountUsdCents: int, externalReference?: string, returnUrl?: string, expiresInMinutes?: int}
     */
    public function toArray(): array
    {
        $body = ['amountUsdCents' => $this->amountUsdCents];
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
