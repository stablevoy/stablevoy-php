<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** How many active API keys one store may hold per integration; revoked keys do not count. */
final class ApiKeysPerStore
{
    public function __construct(
        /** Keys of one plugin (WooCommerce, Easy Digital Downloads, WHMCS). */
        public readonly int $plugin,
        /** AI assistant (MCP) keys, one per computer. */
        public readonly int $mcp,
        /** Keys for your own code. */
        public readonly int $api,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->int('plugin'), $f->int('mcp'), $f->int('api'));
    }
}
