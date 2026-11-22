<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The steps one invoice went through, oldest first: created, what the payment page reported, the first payment seen and the outcome. */
final class CheckoutTrail
{
    /**
     * @param list<CheckoutTrailStep> $steps
     */
    public function __construct(
        public readonly string $invoiceId,
        public readonly array $steps,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $steps = [];
        foreach ($f->list('steps') as $i => $item) {
            $steps[] = CheckoutTrailStep::fromFields(Fields::of($item, $f->path('steps') . "[$i]"));
        }
        return new self($f->string('invoiceId'), $steps);
    }
}
