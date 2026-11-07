<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** Totals of the store's invoices created in a range of UTC days. */
final class InvoiceSummary
{
    /**
     * @param array<string, int> $invoicesByStatus how many of them are in each status now, every status included (see InvoiceStatus)
     * @param list<NetworkTotal> $byNetwork
     */
    public function __construct(
        /** The first and last day counted, YYYY-MM-DD, also when a period was asked for. */
        public readonly string $createdFrom,
        public readonly string $createdTo,
        public readonly int $invoiceCount,
        public readonly array $invoicesByStatus,
        /** Sum of their amounts in US cents, cancelled and expired invoices left out. */
        public readonly int $invoicedUsdCents,
        /** The money that counts for them (confirmed and screened clear), in millionths of a dollar, as a string. */
        public readonly string $receivedUsdMicros,
        public readonly array $byNetwork,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $invoices = $f->object('invoices');
        $byStatus = $invoices->object('byStatus');
        $counts = [];
        foreach (array_keys($byStatus->raw()) as $status) {
            $counts[(string) $status] = $byStatus->int((string) $status);
        }
        $byNetwork = array_map(
            static fn(mixed $item, int $i): NetworkTotal => NetworkTotal::fromFields(Fields::of($item, $f->path('byNetwork') . "[$i]")),
            $f->list('byNetwork'),
            array_keys($f->list('byNetwork')),
        );
        return new self(
            $f->string('createdFrom'),
            $f->string('createdTo'),
            $invoices->int('count'),
            $counts,
            $f->int('invoicedUsdCents'),
            $f->string('receivedUsdMicros'),
            $byNetwork,
        );
    }
}
