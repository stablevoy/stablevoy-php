<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** One page of invoices, newest first; pass nextCursor to get the next page, null on the last one. */
final class InvoicePage
{
    /**
     * @param list<Invoice> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $items = $f->list('items');
        return new self(
            array_map(static fn(mixed $item, int $i): Invoice => Invoice::fromFields(Fields::of($item, $f->path('items') . "[$i]")), $items, array_keys($items)),
            $f->nullableString('nextCursor'),
        );
    }
}
