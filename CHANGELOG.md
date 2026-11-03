# Changelog

## Unreleased

- `InvoiceFilter` counted the search length in bytes, so a search of 101 to 200 Cyrillic letters was refused although the API accepts it. It now counts as the API does (UTF-16 units: one per letter, two per emoji), and refuses text that is not UTF-8.
- Webhook events now carry `source` on their invoice, as the API answers do. Events recorded before 3 November 2026 have none, and still verify.
- One-click connect: `ConnectClient::exchange()` redeems a connect code for the shop's API key and webhook secret (`ConnectedStore`). It needs no API key and is never retried, since the code works once.
- `Stores::revokeCurrentKey()` revokes the key of the client, for a shop that disconnects. It is retried like a read.
- `Transport` has `delete()`. Your own implementation of `Transport` needs it too.
- Reads, and creates that carry an idempotency key, are also retried on the gateway errors 502, 504 and 520 to 524. nginx and Cloudflare answer with them for the few seconds the API restarts, so a call made then no longer fails at once. A 500 is still not retried.

## 1.3.0

- `Store` carries `networksWithoutPayout`: the enabled networks with no payout wallet for their family (EVM or Tron) on the account. Buyers are not offered them.
- `Store::canAcceptPayments()` is false when no enabled network has a payout wallet, so every invoice would be refused. Check it before offering the payment method.
- `ApiException::PAYOUT_ADDRESS_NOT_SET` names the error code an invoice gets in that case.

## 1.2.0

- `Invoices::list()` and `all()` take an `InvoiceFilter`: status, source (payment link, WooCommerce, Easy Digital Downloads, WHMCS, an API key made by hand, or not recorded), created between two UTC days, and a search by invoice id or the start of the external reference. The `$status` argument still works.
- Invoices carry `source` (`InvoiceSource`): how the invoice was made and, for a key, which one, on `list()`, `all()`, `get()` and `create()`.

## 1.1.0

- `Client::create()` now talks over ext-curl with a timeout: every attempt is limited to 30 seconds from connecting to the last byte (`timeoutSeconds`, at most 600). Before, it used whatever PSR-18 client was installed, and Guzzle, for one, has no timeout by default, so a server that never answered could hang a request forever. A timed-out attempt is retried where a repeat is safe, otherwise reported as a `TransportException`.
- Requires ext-curl. A PSR-18 client is no longer needed for `Client::create()`, only PSR-17 message factories (Guzzle and Symfony projects have them, or `nyholm/psr7`). `Client::fromPsr()` is unchanged; set the timeout on the client you pass.
- Security review of the new client: redirects are never followed, certificates and host names are always verified, only http and https are spoken (so a `file://` URL cannot be read), and curl error messages never carry the API key.

## 1.0.1

Security hardening:

- The base URL must be https and only a host (an optional port is fine); plain http is accepted only for localhost, and URLs with credentials, a path or a query are refused, so the API key is never sent unencrypted or to an unexpected place.
- Invoice ids must be UUIDs. Before, an id such as `..` produced a request to another path of the API with your key.
- CI actions are pinned to commits.

## 1.0.0

First release: invoices (create with an idempotency key, read, list and walk all pages), the store of the API key, limits, pricing and networks, typed errors with retries where a repeat is safe, and webhook signature verification.
