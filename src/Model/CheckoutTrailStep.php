<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** One step of an invoice on the payment page. */
final class CheckoutTrailStep
{
    public function __construct(
        /** See CheckoutStep. */
        public readonly string $step,
        /** When the step was first reached. */
        public readonly \DateTimeImmutable $at,
        /** Where the page ran (see CheckoutSurface), only on opened; null otherwise. */
        public readonly ?string $surface,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('step'), $f->date('at'), $f->nullableString('surface'));
    }
}
