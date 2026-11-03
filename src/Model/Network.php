<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A network the platform accepts payments on. settlement is "forwarder" (deposit contracts) or "direct" (Tron: the merchant's own wallet). */
final class Network
{
    /**
     * @param list<Token> $tokens
     */
    public function __construct(
        public readonly string $key,
        public readonly string $displayName,
        public readonly string $family,
        public readonly int $chainId,
        public readonly int $confirmations,
        public readonly string $settlement,
        public readonly ?string $factoryAddress,
        public readonly ?string $explorerUrl,
        public readonly array $tokens,
        /** The forwarder contract the deposit addresses clone; with factoryAddress it is what a manual recovery needs. */
        public readonly ?string $forwarderImplementation,
        /** The explorer page of an address is this followed by the address. */
        public readonly ?string $explorerAddressUrl,
        /** A public RPC endpoint of the network. */
        public readonly ?string $rpcUrl,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $tokens = $f->list('tokens');
        return new self(
            $f->string('key'),
            $f->string('displayName'),
            $f->string('family'),
            $f->int('chainId'),
            $f->int('confirmations'),
            $f->string('settlement'),
            $f->nullableString('factoryAddress'),
            $f->nullableString('explorerUrl'),
            array_map(static fn(mixed $item, int $i): Token => Token::fromFields(Fields::of($item, $f->path('tokens') . "[$i]")), $tokens, array_keys($tokens)),
            $f->nullableString('forwarderImplementation'),
            $f->nullableString('explorerAddressUrl'),
            $f->nullableString('rpcUrl'),
        );
    }
}
