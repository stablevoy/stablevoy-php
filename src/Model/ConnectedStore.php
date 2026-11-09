<?php

declare(strict_types=1);

namespace Stablevoy\Model;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Key\KeyCheck;
use Stablevoy\Key\KeyFormat;

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
        if (KeyFormat::checkApiKey($apiKey) !== KeyCheck::Ok) {
            throw new UnexpectedResponseException($f->path('apiKey') . ' is not an API key (svk_live_, svk_test_, or the older sk_live_, sk_test_)');
        }
        $secret = $f->string('webhookSecret');
        if (KeyFormat::checkWebhookSecret($secret) !== KeyCheck::Ok) {
            throw new UnexpectedResponseException($f->path('webhookSecret') . ' is not a webhook secret (svwhsec_, or the older whsec_)');
        }
        $store = $f->object('store');
        return new self($apiKey, $secret, $store->string('id'), $store->string('name'));
    }
}
