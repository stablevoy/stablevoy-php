<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/**
 * The API answered with an error: an RFC 7807 problem document with a stable code, a readable detail and a link to
 * the code's entry in the error catalog (https://stablevoy.com/docs/api/errors/).
 */
class ApiException extends \RuntimeException implements StablevoyException
{
    /** No enabled network of the store has a payout wallet; Store::canAcceptPayments() tells it in advance. */
    public const PAYOUT_ADDRESS_NOT_SET = 'payout_address_not_set';

    /**
     * The EVM payout wallet is not confirmed in the dashboard (or changed since) and the store has no other network to
     * offer; Store::evmPayoutNeedsReview() and Store::canAcceptPayments() tell it in advance.
     */
    public const PAYOUT_ADDRESS_UNACKNOWLEDGED = 'payout_address_unacknowledged';

    /** Invoices::cancel() on an invoice that already has a payment seen on chain, or is paid, expired, underpaid or on hold. */
    public const INVOICE_NOT_CANCELLABLE = 'invoice_not_cancellable';

    /** expiresInMinutes is outside the bounds Platform::limits() gives (15 to 10080 minutes). */
    public const INVALID_INVOICE_LIFETIME = 'invalid_invoice_lifetime';

    /**
     * @param list<array{path: string, message: string}> $issues the fields a validation error names
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        public readonly string $detail,
        public readonly ?string $type,
        public readonly array $issues,
        public readonly ?int $retryAfterSeconds,
        /** For an amount refused by a limit (amount_below_minimum, amount_above_store_limit, ...): that limit in US cents. */
        public readonly ?int $limitUsdCents = null,
    ) {
        parent::__construct(sprintf('%s (HTTP %d, %s)', $detail, $status, $errorCode), $status);
    }
}
