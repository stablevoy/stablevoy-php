<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * The steps of the payment page funnel and trail. Responses carry them as plain strings, so a step added to the API later
 * reaches you as its name instead of breaking this library; compare with these constants.
 */
final class CheckoutStep
{
    /** The invoice was created (trail only). */
    public const CREATED = 'created';
    /** The buyer opened the payment page or the widget. */
    public const OPENED = 'opened';
    /** The buyer picked another network or token. */
    public const OPTION_CHANGED = 'option_changed';
    /** The buyer copied the amount or the address. */
    public const DETAILS_COPIED = 'details_copied';
    public const WALLET_STARTED = 'wallet_started';
    /** The buyer cancelled the payment in the wallet. */
    public const WALLET_REJECTED = 'wallet_rejected';
    public const WALLET_FAILED = 'wallet_failed';
    /** The wallet sent the transaction. */
    public const TX_SENT = 'tx_sent';
    /** The first payment was seen on chain (trail only). */
    public const DETECTED = 'detected';
    public const PAID = 'paid';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
}
