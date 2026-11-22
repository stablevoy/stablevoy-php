<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A range of UTC days as Invoices::summary() and Stores::checkoutFunnel() take it: a period, or both days. */
final class SummaryRange
{
    /**
     * The query of the range, refused here when it is not one the API takes.
     *
     * @return array<string, string>
     */
    public static function query(?string $period, ?string $createdFrom, ?string $createdTo): array
    {
        $hasDays = $createdFrom !== null || $createdTo !== null;
        if (($period !== null) === $hasDays) {
            throw new \InvalidArgumentException('Pass either period or both createdFrom and createdTo');
        }
        if ($period !== null) {
            if (!in_array($period, SummaryPeriod::ALL, true)) {
                throw new \InvalidArgumentException('period must be one of ' . implode(', ', SummaryPeriod::ALL));
            }
            return ['period' => $period];
        }
        $query = [];
        foreach (['createdFrom' => $createdFrom, 'createdTo' => $createdTo] as $name => $day) {
            if ($day === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                throw new \InvalidArgumentException("$name must be a date as YYYY-MM-DD");
            }
            $query[$name] = $day;
        }
        return $query;
    }
}
