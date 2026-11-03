<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * Which invoices Invoices::list() and all() return. Every part is optional and they combine. Days are whole UTC days
 * as YYYY-MM-DD, both ends included. The search is an exact invoice id or the start of the external reference.
 */
final class InvoiceFilter
{
    private const DAY = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(
        /** See InvoiceStatus. */
        public readonly ?string $status = null,
        /** InvoiceSource::PAYMENT_LINK, WOOCOMMERCE, EDD, WHMCS, API_KEY or NOT_RECORDED. */
        public readonly ?string $source = null,
        public readonly ?string $createdFrom = null,
        public readonly ?string $createdTo = null,
        public readonly ?string $search = null,
    ) {
        foreach (['createdFrom' => $createdFrom, 'createdTo' => $createdTo] as $name => $day) {
            if ($day !== null && preg_match(self::DAY, $day) !== 1) {
                throw new \InvalidArgumentException("$name must be a date as YYYY-MM-DD");
            }
        }
        if ($search !== null) {
            $length = self::utf16Length($search);
            if ($length === null || $length < 1 || $length > 200) {
                throw new \InvalidArgumentException('search must be 1 to 200 characters of UTF-8 text');
            }
        }
    }

    /**
     * The length the API checks: UTF-16 units, as JavaScript counts them, so a character outside the basic plane (an
     * emoji) counts twice. Null for a string that is not UTF-8.
     */
    private static function utf16Length(string $text): ?int
    {
        $characters = preg_match_all('/./su', $text);
        if ($characters === false) {
            return null;
        }
        return $characters + (int) preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $text);
    }

    /**
     * @return array<string, string> the query parameters of the parts that are set
     */
    public function toQuery(): array
    {
        $query = ['status' => $this->status, 'source' => $this->source, 'createdFrom' => $this->createdFrom, 'createdTo' => $this->createdTo, 'q' => $this->search];
        return array_filter($query, static fn(?string $value): bool => $value !== null);
    }
}
