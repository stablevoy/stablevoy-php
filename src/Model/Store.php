<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The store the API key belongs to. */
final class Store
{
    /**
     * @param list<string> $enabledNetworks network keys, e.g. "base", "tron"
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
    ) {}

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
        );
    }
}
