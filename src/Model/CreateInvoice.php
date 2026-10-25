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
    ) {
        if ($amountUsdCents < 1) {
            throw new \InvalidArgumentException('amountUsdCents must be a positive number of US cents, for example 1999 for $19.99');
        }
    }

    /**
     * @return array{amountUsdCents: int, externalReference?: string, returnUrl?: string}
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
        return $body;
    }
}
