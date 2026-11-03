<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The store the API key belongs to. */
final class Store
{
    /**
     * @param list<string> $enabledNetworks network keys, e.g. "base", "tron"
     * @param list<string> $networksWithoutPayout enabled networks with no payout wallet for their family (EVM or Tron) on the
     *                                            account: buyers are not offered them
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
    ) {}

    /**
     * False when no enabled network has a payout wallet: every invoice would be refused with payout_address_not_set.
     * Check it before offering the payment method, and show the merchant networksWithoutPayout when it is not empty.
     */
    public function canAcceptPayments(): bool
    {
        return array_diff($this->enabledNetworks, $this->networksWithoutPayout) !== [];
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
        );
    }
}
