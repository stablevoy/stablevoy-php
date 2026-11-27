<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\TestCase;
use Stablevoy\Checkout\CheckoutLink;
use Stablevoy\Checkout\PayoutAnchor;
use Stablevoy\Exception\PayoutMismatchException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\CreatedInvoice;
use Stablevoy\Model\Fields;
use Stablevoy\Tests\Support\OpenApi;

final class PayoutAnchorTest extends TestCase
{
    private const EVM = '0x5cc5bc5cc5bc5cc5bc5cc5bc5cc5bc5cc5bc5cc5';
    private const TRON = 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8';

    /** The documented answer of POST /v1/invoices, with the payout wallets set per network. */
    private static function created(string $evmPayout, ?string $tronPayout = null, ?string $checkoutUrl = null): CreatedInvoice
    {
        $example = OpenApi::example('POST', '/v1/invoices', 201);
        self::assertIsArray($example['depositAddresses']);
        $first = $example['depositAddresses'][0];
        self::assertIsArray($first);
        $addresses = [array_merge($first, ['network' => 'base', 'payoutAddress' => $evmPayout])];
        if ($tronPayout !== null) {
            $addresses[] = array_merge($first, ['network' => 'tron', 'address' => $tronPayout, 'payoutAddress' => $tronPayout]);
        }
        $example['depositAddresses'] = $addresses;
        if ($checkoutUrl !== null) {
            $example['checkoutUrl'] = $checkoutUrl;
        }
        return CreatedInvoice::fromFields(Fields::of($example, 'invoice'));
    }

    public function testBuildsTheFragmentAndRefusesWhatIsNotAWallet(): void
    {
        self::assertSame('payout=evm:' . self::EVM . ';tron:' . self::TRON, (new PayoutAnchor(self::EVM, self::TRON))->fragment());
        self::assertSame('payout=tron:' . self::TRON, (new PayoutAnchor(tron: self::TRON))->fragment());
        $refused = 0;
        foreach ([[null, null], ['0x12', null], [null, 'T123'], [self::EVM . 'ff', null]] as [$evm, $tron]) {
            try {
                new PayoutAnchor($evm, $tron);
            } catch (\InvalidArgumentException) {
                $refused++;
            }
        }
        self::assertSame(4, $refused);
    }

    public function testComparesEvmWithoutCaseAndTronExactly(): void
    {
        $anchor = new PayoutAnchor('0x' . strtoupper(substr(self::EVM, 2)), self::TRON);
        self::assertTrue($anchor->accepts(self::EVM));
        self::assertTrue($anchor->accepts(self::TRON));
        self::assertFalse($anchor->accepts(strtolower(self::TRON)));
        self::assertFalse((new PayoutAnchor(tron: self::TRON))->accepts(self::EVM));
        self::assertFalse($anchor->accepts('not an address'));
    }

    public function testBuildsTheLinkFromTheSiteAndTheInvoiceId(): void
    {
        $created = self::created(self::EVM);
        $link = (new CheckoutLink())->for($created, new PayoutAnchor(evm: self::EVM));
        self::assertSame('https://stablevoy.com/pay/?i=' . $created->invoice->id . '#payout=evm:' . self::EVM, $link->url);
        self::assertSame([], $link->mismatchedNetworks);
    }

    public function testReportsTheNetworksWhoseWalletChangedAndStopsWhenNoneIsLeft(): void
    {
        $partly = (new CheckoutLink())->for(self::created(self::EVM, self::TRON), new PayoutAnchor(evm: self::EVM));
        self::assertSame(['tron'], $partly->mismatchedNetworks);
        $this->expectException(PayoutMismatchException::class);
        $this->expectExceptionMessage('base: 0x' . str_repeat('99', 20));
        (new CheckoutLink())->for(self::created('0x' . str_repeat('99', 20)), new PayoutAnchor(evm: self::EVM));
    }

    public function testRefusesAPaymentLinkOfAnotherSite(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        (new CheckoutLink())->for(self::created(self::EVM, checkoutUrl: 'https://evil.example/pay/?i=x'), new PayoutAnchor(evm: self::EVM));
    }

    public function testTakesALocalSiteAndRefusesAnythingButABareHttpsOrigin(): void
    {
        $created = self::created(self::EVM, checkoutUrl: 'https://stablevoy.test:8443/pay/?i=x');
        self::assertStringStartsWith('https://stablevoy.test:8443/pay/?i=', (new CheckoutLink('https://Stablevoy.test:8443'))->for($created, new PayoutAnchor(evm: self::EVM))->url);
        $refused = 0;
        foreach (['http://stablevoy.com', 'https://stablevoy.com/pay', 'https://u@stablevoy.com', 'https://stablevoy.com?x=1', 'stablevoy.com'] as $bad) {
            try {
                new CheckoutLink($bad);
            } catch (\InvalidArgumentException) {
                $refused++;
            }
        }
        self::assertSame(5, $refused);
    }
}
