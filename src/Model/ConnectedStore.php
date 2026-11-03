<?php

declare(strict_types=1);

namespace Stablevoy\Model;

use Stablevoy\Exception\UnexpectedResponseException;

/** What a redeemed connect code gives the shop: its API key, its webhook signing secret and the store they belong to. */
final class ConnectedStore
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $webhookSecret,
        public readonly string $storeId,
        public readonly string $storeName,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $apiKey = $f->string('apiKey');
        if (preg_match('/^sk_(live|test)_/', $apiKey) !== 1) {
            throw new UnexpectedResponseException($f->path('apiKey') . ' is not an API key (sk_live_ or sk_test_)');
        }
        $secret = $f->string('webhookSecret');
        if (!str_starts_with($secret, 'whsec_')) {
            throw new UnexpectedResponseException($f->path('webhookSecret') . ' is not a webhook secret (whsec_)');
        }
        $store = $f->object('store');
        return new self($apiKey, $secret, $store->string('id'), $store->string('name'));
    }
}
