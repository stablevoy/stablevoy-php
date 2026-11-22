<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Http\Transport;
use Stablevoy\Model\CheckoutFunnel;
use Stablevoy\Model\Fields;
use Stablevoy\Model\Store;
use Stablevoy\Model\SummaryRange;

final class Stores
{
    public function __construct(private readonly Transport $transport) {}

    /** The store the API key belongs to: a cheap way to check that the key works. */
    public function current(): Store
    {
        return Store::fromFields(Fields::of($this->transport->get('/v1/store'), 'store'));
    }

    /**
     * Where buyers stop on the payment page, for the store's invoices created in a range of UTC days: how many opened the
     * page, changed the network or token, copied the payment details, started a wallet, had it refused or failed, sent the
     * transaction, saw a payment and were paid. The same range as Invoices::summary(): a period (SummaryPeriod) or both
     * days, at most 92 days. Counts of invoices, not of visits. The API allows 30 calls a minute per store.
     */
    public function checkoutFunnel(?string $period = null, ?string $createdFrom = null, ?string $createdTo = null): CheckoutFunnel
    {
        $query = SummaryRange::query($period, $createdFrom, $createdTo);
        return CheckoutFunnel::fromFields(Fields::of($this->transport->get('/v1/store/checkout-funnel', $query), 'checkoutFunnel'));
    }

    /**
     * Revokes the API key of this client; it stops working at once (use it when the shop disconnects). A key that is
     * already revoked answers with AuthenticationException, which for a disconnect means the same.
     */
    public function revokeCurrentKey(): void
    {
        $this->transport->delete('/v1/store/api-keys/current');
    }
}
