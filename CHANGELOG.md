# Changelog

## 1.6.0

- `Invoices::cancel($id)` cancels an invoice that is still waiting for its first payment: the payment page tells the buyer not to pay and the webhook `invoice.cancelled` is sent. Cancelling it again returns it unchanged. Once a payment has been seen the API refuses with `ApiException::INVOICE_NOT_CANCELLABLE`; money that still arrives after a cancel makes the invoice `paid_late`. New status `InvoiceStatus::CANCELLED` and webhook type `invoice.cancelled`.
- `CreateInvoice` takes `expiresInMinutes`: how long the buyer has to pay this invoice, 15 to 10080 minutes (7 days). Outside the bounds the API answers `ApiException::INVALID_INVOICE_LIFETIME`.
- `Store::$invoiceLifetimeMinutes`: the store's payment window, null for the platform default. `Limits` carries `defaultInvoiceLifetimeMinutes`, `minInvoiceLifetimeMinutes` and `maxInvoiceLifetimeMinutes`.
- `Store::$payoutAddresses` (`PayoutAddresses` with `evm` and `tron`): the account's payout wallets per network family, null when not set. Read only; they are changed in the dashboard with a wallet signature.
- These fields need the API of 6 November 2026; an older answer without them is refused as an unexpected response.

## 1.5.0

- `Store` carries `evmPayoutStatus` (`plain`, `acknowledged`, `unacknowledged`, `unchecked`, or null without an EVM payout wallet, with `Store::EVM_PAYOUT_*` constants) and `evmNetworksBlocked`: the enabled EVM networks new invoices leave out while the EVM payout wallet is not confirmed in the dashboard. `Store::evmPayoutNeedsReview()` tells it.
- `Store::canAcceptPayments()` also counts the blocked EVM networks, so it is false for a store whose only networks are blocked.
- `ApiException::PAYOUT_ADDRESS_UNACKNOWLEDGED` names the error code an invoice gets when the EVM payout wallet is not confirmed and the store has no other network.
- New webhook type `store.payout_attention`: the EVM payout wallet of the account became unconfirmed, or is fine again. It carries no invoice; the data has `store` and `payout` (status, blocked networks, dashboard link).

## 1.4.0

- New webhook type `invoice.payment_flagged`: a payment of an invoice that is already paid, overpaid or paid late was flagged by sanctions screening. The invoice keeps its status; the flagged payment is in the event data under `payment`.
- The curl client keeps only the headers of the final answer. Behind an HTTPS proxy, or after a 1xx answer, it used to add the headers of those answers too, so two Retry-After values could merge and be read as none.
- `Store::$connectedPlatform`: the plugin connected to the store in one click, or null. `Network` carries `forwarderImplementation`, `explorerAddressUrl` and `rpcUrl`. `ApiException::$limitUsdCents` gives the limit an amount was refused for. All three were sent by the API and dropped.
- `Invoices::create()` checks the idempotency key before sending: 1 to 255 printable ASCII characters without spaces, as the API requires. Before, a key with a line break or a non-ASCII letter failed inside the HTTP library with an exception outside `StablevoyException`.
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
