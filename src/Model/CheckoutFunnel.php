<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** Where buyers stop on the payment page, for the store's invoices created in a range of UTC days. Counts of invoices, not of visits. */
final class CheckoutFunnel
{
    /**
     * @param array<string, int> $steps how many of them reached each step of the payment page at least once, by step name (see CheckoutStep)
     * @param array<string, int> $openedOn opened invoices by where the page ran (see CheckoutSurface)
     */
    public function __construct(
        /** The first and last day counted, YYYY-MM-DD, also when a period was asked for. */
        public readonly string $createdFrom,
        public readonly string $createdTo,
        /** Invoices created in the range. */
        public readonly int $invoices,
        public readonly array $steps,
        public readonly array $openedOn,
        /** Invoices with at least one payment seen on chain. */
        public readonly int $detected,
        /** Invoices paid in full, overpaid or paid late. */
        public readonly int $paid,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('createdFrom'),
            $f->string('createdTo'),
            $f->int('invoices'),
            self::counts($f->object('steps')),
            self::counts($f->object('openedOn')),
            $f->int('detected'),
            $f->int('paid'),
        );
    }

    /** @return array<string, int> */
    private static function counts(Fields $o): array
    {
        $counts = [];
        foreach (array_keys($o->raw()) as $name) {
            $counts[(string) $name] = $o->int((string) $name);
        }
        return $counts;
    }
}
