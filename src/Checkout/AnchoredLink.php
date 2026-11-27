<?php

declare(strict_types=1);

namespace Stablevoy\Checkout;

/** A payment link with the shop's payout anchor, and the networks of the invoice whose wallet the anchor does not name. */
final class AnchoredLink
{
    /**
     * @param list<string> $mismatchedNetworks networks the payment page will leave out: tell the merchant their payout wallet changed
     */
    public function __construct(
        public readonly string $url,
        public readonly array $mismatchedNetworks,
    ) {}
}
