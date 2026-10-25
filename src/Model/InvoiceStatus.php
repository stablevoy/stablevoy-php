<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * Invoice statuses. Invoice::$status is a plain string, so a status added by a newer API version is kept, not lost;
 * compare it with these constants.
 */
final class InvoiceStatus
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const CONFIRMING = 'confirming';
    public const PAID = 'paid';
    public const OVERPAID = 'overpaid';
    public const UNDERPAID = 'underpaid';
    public const EXPIRED = 'expired';
    public const PAID_LATE = 'paid_late';
    public const ON_HOLD = 'on_hold';

    /** The order can be fulfilled: the full amount, or more, has arrived and is confirmed. */
    public static function isPaid(string $status): bool
    {
        return in_array($status, [self::PAID, self::OVERPAID, self::PAID_LATE], true);
    }

    private function __construct() {}
}
