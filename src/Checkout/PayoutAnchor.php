<?php

declare(strict_types=1);

namespace Stablevoy\Checkout;

/**
 * The payout wallets your shop confirmed, put in the payment link after # so the Stablevoy payment page refuses any other
 * wallet, even one a compromised server would name. Store it when the merchant confirms the wallets in your shop, never
 * take it from an API answer: that answer is what it guards against.
 */
final class PayoutAnchor
{
    private const EVM = '/^0x[0-9a-fA-F]{40}$/';
    private const TRON = '/^T[1-9A-HJ-NP-Za-km-z]{33}$/';

    public function __construct(
        public readonly ?string $evm = null,
        public readonly ?string $tron = null,
    ) {
        if ($evm === null && $tron === null) {
            throw new \InvalidArgumentException('A payout anchor names at least one wallet (evm or tron)');
        }
        if ($evm !== null && preg_match(self::EVM, $evm) !== 1) {
            throw new \InvalidArgumentException('The EVM payout wallet must be an address like 0x followed by 40 hex characters');
        }
        if ($tron !== null && preg_match(self::TRON, $tron) !== 1) {
            throw new \InvalidArgumentException('The Tron payout wallet must be a Base58 address starting with T');
        }
    }

    /** The part after # of the payment link: payout=evm:0x...;tron:T... */
    public function fragment(): string
    {
        $parts = [];
        if ($this->evm !== null) {
            $parts[] = 'evm:' . $this->evm;
        }
        if ($this->tron !== null) {
            $parts[] = 'tron:' . $this->tron;
        }
        return 'payout=' . implode(';', $parts);
    }

    /**
     * Whether a payout address of an invoice is the anchored wallet of its family: EVM compared without regard to letter
     * case, Tron exactly. An address of no known form matches nothing.
     */
    public function accepts(string $payoutAddress): bool
    {
        if (preg_match(self::EVM, $payoutAddress) === 1) {
            return $this->evm !== null && strtolower($this->evm) === strtolower($payoutAddress);
        }
        if (preg_match(self::TRON, $payoutAddress) === 1) {
            return $this->tron !== null && $this->tron === $payoutAddress;
        }
        return false;
    }
}
