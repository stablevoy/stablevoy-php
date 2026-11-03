# Stablevoy PHP SDK

[![CI](https://github.com/stablevoy/stablevoy-php/actions/workflows/ci.yml/badge.svg)](https://github.com/stablevoy/stablevoy-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/stablevoy/stablevoy-php)](https://packagist.org/packages/stablevoy/stablevoy-php)

PHP client for [Stablevoy](https://stablevoy.com), the non-custodial gateway for USDT and USDC payments. Customers pay on chain and the money goes straight to your own wallet: USDC and USDT on Base, Arbitrum, Optimism, Avalanche, Polygon and BNB Chain, and USDT (TRC20) on Tron with no fee.

The library creates invoices, reads them, and verifies the signed webhooks that tell your shop an order is paid. It talks to the API over ext-curl, or over any PSR-18 HTTP client you pass, and needs PHP 8.1 or newer.

## Install

```bash
composer require stablevoy/stablevoy-php
```

The library needs PSR-17 HTTP message factories. Projects with Guzzle or Symfony already have them; otherwise add `composer require nyholm/psr7`.

## Create an invoice and send the buyer to pay

Create an API key for your store in the [Stablevoy dashboard](https://stablevoy.com/app/).

```php
use Stablevoy\Client;
use Stablevoy\Model\CreateInvoice;

$stablevoy = Client::create($_ENV['STABLEVOY_API_KEY']);

$created = $stablevoy->invoices()->create(
    new CreateInvoice(
        amountUsdCents: 2599,                 // $25.99
        externalReference: 'order-1042',      // your order id, sent back in webhooks
        returnUrl: 'https://shop.example/orders/1042',
    ),
    idempotencyKey: 'order-1042',             // a retry returns the same invoice, never a second one
);

header('Location: ' . $created->checkoutUrl);
```

The buyer pays on the hosted payment page. Read an invoice at any time with `$stablevoy->invoices()->get($id)`, or list them:

```php
foreach ($stablevoy->invoices()->all(status: 'paid') as $invoice) {
    echo $invoice->externalReference, ' ', $invoice->amountUsdCents, PHP_EOL;
}
```

Narrow the list by source, created days (UTC, both ends included) or a search by invoice id or the start of your order reference. Each invoice says where it came from:

```php
use Stablevoy\Model\InvoiceFilter;
use Stablevoy\Model\InvoiceSource;

$filter = new InvoiceFilter(source: InvoiceSource::PAYMENT_LINK, createdFrom: '2026-11-01', createdTo: '2026-11-30');
foreach ($stablevoy->invoices()->all(filter: $filter) as $invoice) {
    echo $invoice->id, ' ', $invoice->source?->kind, PHP_EOL;
}
```

## Check the store before going live

Invoices need a payout wallet for at least one network the store accepts; without one every invoice is refused with `payout_address_not_set`. Read the store of the key to find out in advance, and tell the merchant about networks buyers will not be offered:

```php
$store = $stablevoy->stores()->current();
if (!$store->canAcceptPayments()) {
    // Hide the payment method: set the payout address in the Stablevoy dashboard first.
}
if ($store->networksWithoutPayout !== []) {
    // These enabled networks have no payout wallet, so buyers do not see them.
}
```

## Connect a shop in one click

A shop plugin can get its API key and webhook secret without the merchant copying them: it sends the merchant to the Stablevoy dashboard with a PKCE challenge, gets a code back on its callback URL and redeems it. This call needs no API key, since the shop has none yet. The code works once, so the call is never retried.

```php
use Stablevoy\Connect\ConnectClient;

$connected = ConnectClient::create()->exchange($code, $codeVerifier, 'https://shop.example.com');
// Store $connected->apiKey and $connected->webhookSecret; $connected->storeName is the store the merchant chose.
```

When the merchant disconnects the shop, give the key up so it stops working at once. A key that is already revoked answers with `AuthenticationException`, which for a disconnect means the same:

```php
$stablevoy->stores()->revokeCurrentKey();
```

## Verify webhooks

Stablevoy posts an event to your webhook URL when an invoice is paid, overpaid, underpaid, expired, paid late or put on hold. Check the signature before you trust it, using the signing secret shown when you set the webhook in the dashboard. Pass the raw body, not decoded JSON.

```php
use Stablevoy\Exception\SignatureException;
use Stablevoy\Webhook\Verifier;

$verifier = new Verifier($_ENV['STABLEVOY_WEBHOOK_SECRET']);

try {
    $event = $verifier->verify(file_get_contents('php://input'), $_SERVER['HTTP_X_STABLEVOY_SIGNATURE'] ?? '');
} catch (SignatureException $e) {
    http_response_code(400);
    exit;
}

if ($event->invoice !== null && $event->invoice->isPaid()) {
    // Mark the order $event->invoice->externalReference as paid. Use $event->id to handle each event once.
}
http_response_code(200);
```

In Laravel the same check is `$verifier->verify($request->getContent(), $request->header('X-Stablevoy-Signature', ''))`.

## Errors and retries

Every exception implements `Stablevoy\Exception\StablevoyException`. API errors are `ApiException` with the HTTP status, a stable `errorCode`, a readable `detail` and a link to the [error catalog](https://stablevoy.com/docs/api/errors/); the ones you may want to handle on their own are `AuthenticationException`, `ValidationException` (with `issues`), `NotFoundException`, `RateLimitedException` and `MaintenanceException`. `TransportException` means no answer at all.

Reads, and creates that carry an idempotency key, are retried on rate limits, maintenance, gateway errors while the API restarts (502, 504, 520 to 524) and failed connections, at most twice and only for waits of up to 10 seconds. Anything longer reaches you with `retryAfterSeconds`, so a request never hangs. Change it with `ClientOptions`:

```php
use Stablevoy\ClientOptions;
use Stablevoy\Http\RetryPolicy;

$stablevoy = Client::create($apiKey, new ClientOptions(retries: new RetryPolicy(maxRetries: 3, maxWaitSeconds: 30)));
```

## Timeout

Every attempt is limited to 30 seconds, from connecting to the last byte of the answer, so a server that does not answer cannot hang your checkout. A timed-out attempt is retried where a repeat is safe, otherwise it is a `TransportException`. Set another limit, up to 600 seconds, as the third argument:

```php
$stablevoy = Client::create($apiKey, new ClientOptions(), timeoutSeconds: 10);
```

## Your own HTTP client

```php
$stablevoy = Client::fromPsr($apiKey, $psr18Client, $requestFactory, $streamFactory);
```

Then the timeout is your client's setting. Set one: Guzzle, for example, waits forever by default (`new GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10])`).

## Links

- [API reference](https://stablevoy.com/docs/api/)
- [Docs](https://stablevoy.com/docs/)
- [WooCommerce plugin](https://wordpress.org/plugins/stablevoy-for-woocommerce/)

## License

MIT
