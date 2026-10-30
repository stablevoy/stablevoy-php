<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * Where an invoice came from (Invoice::$source). The constants are also the values of InvoiceFilter::$source.
 */
final class InvoiceSource
{
    /** Made in the dashboard as a payment link. */
    public const PAYMENT_LINK = 'payment_link';
    /** Made by a plugin connected in one click; $platform names it (woocommerce, edd, whmcs). */
    public const PLUGIN = 'plugin';
    /** Made with an API key created by hand, such as your own code. */
    public const API_KEY = 'api_key';
    /** Made before sources were recorded (31 October 2026). */
    public const NOT_RECORDED = 'not_recorded';

    public const WOOCOMMERCE = 'woocommerce';
    public const EDD = 'edd';
    public const WHMCS = 'whmcs';

    public function __construct(
        /** One of PAYMENT_LINK, PLUGIN, API_KEY, NOT_RECORDED; a kind added by a newer API version is kept as it is. */
        public readonly string $kind,
        /** The plugin, for PLUGIN. */
        public readonly ?string $platform,
        /** The key that made the invoice, for PLUGIN and API_KEY. */
        public readonly ?string $apiKeyId,
        /** The key's name, for API_KEY. */
        public readonly ?string $apiKeyName,
    ) {}

    /** The API sends null for an invoice made before sources were recorded. */
    public static function fromNullableFields(?Fields $f): self
    {
        if ($f === null) {
            return new self(self::NOT_RECORDED, null, null, null);
        }
        $kind = $f->string('kind');
        return new self(
            $kind,
            $kind === self::PLUGIN ? $f->string('platform') : null,
            $kind === self::PLUGIN || $kind === self::API_KEY ? $f->string('apiKeyId') : null,
            $kind === self::API_KEY ? $f->string('apiKeyName') : null,
        );
    }
}
