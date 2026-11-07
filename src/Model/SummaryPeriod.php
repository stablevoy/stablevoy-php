<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The ready-made ranges of Invoices::summary(): UTC days ending today; LAST_3_MONTHS is the last 92 days. */
final class SummaryPeriod
{
    public const LAST_7_DAYS = 'last_7_days';
    public const LAST_30_DAYS = 'last_30_days';
    public const THIS_MONTH = 'this_month';
    public const LAST_MONTH = 'last_month';
    public const LAST_3_MONTHS = 'last_3_months';

    public const ALL = [self::LAST_7_DAYS, self::LAST_30_DAYS, self::THIS_MONTH, self::LAST_MONTH, self::LAST_3_MONTHS];
}
