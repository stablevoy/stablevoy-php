<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The store the API key belongs to. */
final class Store
{
    public const EVM_PAYOUT_PLAIN = 'plain';
    public const EVM_PAYOUT_ACKNOWLEDGED = 'acknowledged';
    public const EVM_PAYOUT_UNACKNOWLEDGED = 'unacknowledged';
    public const EVM_PAYOUT_UNCHECKED = 'unchecked';

    /**
     * @param list<string> $enabledNetworks network keys, e.g. "base", "tron"
     * @param list<string> $networksWithoutPayout enabled networks with no payout wallet for their family (EVM or Tron),
     *                                            neither the store's own nor the account's: buyers are not offered them
     * @param list<string> $evmNetworksBlocked enabled EVM networks new invoices leave out because $evmPayoutStatus is
     *                                         unacknowledged; empty otherwise
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly array $enabledNetworks,
        public readonly ?int $minInvoiceUsdCents,
        public readonly ?int $maxInvoiceUsdCents,
        public readonly ?string $webhookUrl,
        public readonly ?string $websiteUrl,
        public readonly ?string $defaultReturnUrl,
        public readonly \DateTimeImmutable $createdAt,
        public readonly array $networksWithoutPayout,
        /** The plugin connected to the store in one click (woocommerce, edd, whmcs), or null. */
        public readonly ?string $connectedPlatform,
        /**
         * The EVM payout wallet this store uses (its own or the account's) as last read on every EVM network: plain (a regular wallet), acknowledged (a smart contract
         * wallet or EIP-7702 delegation the merchant confirmed), unacknowledged (not confirmed, or changed since: the EVM
         * networks are left out of new invoices until the merchant reviews it in the dashboard), unchecked (not read yet),
         * or null when the store has no EVM payout wallet.
         */
        public readonly ?string $evmPayoutStatus,
        public readonly array $evmNetworksBlocked,
        /** The payout wallets this store's new invoices use per network family: the store's own, else the account's. */
        public readonly PayoutAddresses $payoutAddresses,
        /** The store's payment window: how long its invoices can be paid, in minutes, when the request names none; null means the platform default. */
        public readonly ?int $invoiceLifetimeMinutes,
        /** Where each payout wallet comes from: PayoutSources::STORE or PayoutSources::ACCOUNT, null without one. */
        public readonly PayoutSources $payoutSources,
        /** The account's payout wallets, also when the store uses its own. */
        public readonly PayoutAddresses $accountPayoutAddresses,
        /** When the store was archived; null while it is not. The keys of an archived store are revoked. */
        public readonly ?\DateTimeImmutable $archivedAt,
    ) {}

    /**
     * True while the EVM payout wallet is not confirmed in the Stablevoy dashboard: show the merchant evmNetworksBlocked
     * and send them to the dashboard. A store.payout_attention webhook tells when this changes.
     */
    public function evmPayoutNeedsReview(): bool
    {
        return $this->evmPayoutStatus === self::EVM_PAYOUT_UNACKNOWLEDGED;
    }

    /**
     * False when no enabled network can be paid: none has a payout wallet (invoices are refused with
     * payout_address_not_set), or the only ones left are EVM networks blocked by an unconfirmed EVM payout wallet
     * (payout_address_unacknowledged). Check it before offering the payment method, and show the merchant
     * networksWithoutPayout and evmNetworksBlocked when they are not empty.
     */
    public function canAcceptPayments(): bool
    {
        return array_diff($this->enabledNetworks, $this->networksWithoutPayout, $this->evmNetworksBlocked) !== [];
    }

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('id'),
            $f->string('name'),
            $f->string('status'),
            $f->strings('enabledNetworks'),
            $f->nullableInt('minInvoiceUsdCents'),
            $f->nullableInt('maxInvoiceUsdCents'),
            $f->nullableString('webhookUrl'),
            $f->nullableString('websiteUrl'),
            $f->nullableString('defaultReturnUrl'),
            $f->date('createdAt'),
            $f->strings('networksWithoutPayout'),
            $f->nullableString('connectedPlatform'),
            $f->nullableString('evmPayoutStatus'),
            $f->strings('evmNetworksBlocked'),
            PayoutAddresses::fromFields($f->object('payoutAddresses')),
            $f->nullableInt('invoiceLifetimeMinutes'),
            PayoutSources::fromFields($f->object('payoutSources')),
            PayoutAddresses::fromFields($f->object('accountPayoutAddresses')),
            $f->nullableDate('archivedAt'),
        );
    }
}
