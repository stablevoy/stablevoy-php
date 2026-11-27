<?php

declare(strict_types=1);

namespace Stablevoy\Checkout;

use Stablevoy\Exception\PayoutMismatchException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\CreatedInvoice;

/**
 * Builds the payment link of a new invoice with the shop's payout anchor. The link is built from the configured site and
 * the invoice id, never taken from the answer as it is: a checkoutUrl of another site is refused, so a lying answer cannot
 * send the buyer elsewhere. Networks whose wallet the anchor does not name are reported; with none left it throws.
 */
final class CheckoutLink
{
    private readonly string $siteUrl;

    public function __construct(string $siteUrl = 'https://stablevoy.com')
    {
        $parts = parse_url($siteUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host']) || isset($parts['path']) && $parts['path'] !== '' || isset($parts['query']) || isset($parts['user'])) {
            throw new \InvalidArgumentException('siteUrl must be a bare https origin such as https://stablevoy.com');
        }
        $this->siteUrl = 'https://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public function for(CreatedInvoice $created, PayoutAnchor $anchor): AnchoredLink
    {
        if (!str_starts_with($created->checkoutUrl, $this->siteUrl . '/')) {
            throw new UnexpectedResponseException("The payment link of the answer is not on {$this->siteUrl}; refusing to send the buyer there");
        }
        $mismatched = [];
        $wallets = [];
        foreach ($created->invoice->depositAddresses as $address) {
            if (!$anchor->accepts($address->payoutAddress)) {
                $mismatched[] = $address->network;
                $wallets[] = "{$address->network}: {$address->payoutAddress}";
            }
        }
        if ($created->invoice->depositAddresses !== [] && count($mismatched) === count($created->invoice->depositAddresses)) {
            throw new PayoutMismatchException('No network of the invoice pays to a wallet the shop confirmed (' . $anchor->fragment() . '); the invoice pays to ' . implode(', ', $wallets));
        }
        return new AnchoredLink($this->siteUrl . '/pay/?i=' . rawurlencode($created->invoice->id) . '#' . $anchor->fragment(), $mismatched);
    }
}
