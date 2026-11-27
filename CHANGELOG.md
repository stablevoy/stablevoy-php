# Changelog

## 1.14.0

- Payout wallets in payment links: `PayoutAnchor` (the EVM and Tron wallets your shop confirmed) and `CheckoutLink::for($created, $anchor)`, which returns an `AnchoredLink`: the payment link built from the site and the invoice id with `#payout=...`, and the networks whose wallet the anchor does not name. The Stablevoy payment page then refuses every other wallet, even one a compromised server would name. With no network left it throws `PayoutMismatchException`; a `checkoutUrl` on another site is refused.
- The `store.payout_changed` webhook event (the payout wallet a store uses changed) is documented on `WebhookEvent`.
- The webhook secret is documented as `svwhsec_...`; older `whsec_...` secrets keep working.
- The bundled OpenAPI snapshot lists the new errors `return_url_off_site` and `return_url_without_website` (a return URL must be on the store website) and the key-public sign-in and payout errors.

## 1.13.0

- `Store::$invoiceRange` (`InvoiceRange`): the amounts an invoice of the store may have now, in US cents, with `contains($amountUsdCents)`. It is the account bounds (the platform ones, or a higher or lower cap set for the account on request) narrowed by the store minimum and maximum; use it instead of computing a range from `platform()->limits()`, which shows only the platform default.
- Needs the API with per-account invoice caps (November 2026); an older answer without the field is refused as an unexpected response.

## 1.12.0

- `$client->stores()->checkoutFunnel(period: ...)` or `checkoutFunnel(createdFrom: ..., createdTo: ...)` (`CheckoutFunnel`): where buyers stop on the payment page, for the store's invoices created in a range of UTC days. How many opened the page (and where: `openedOn['page']`, `openedOn['widget']`), changed the network or token, copied the payment details, started a wallet, cancelled it or had it fail, sent the transaction, and how many saw a payment (`detected`) and were paid. The same range rules as `invoices()->summary()`.
- `$client->invoices()->checkoutTrail($invoiceId)` (`CheckoutTrail`, `CheckoutTrailStep`): the steps of one invoice, oldest first, each with the time it was first reached, from its creation to the outcome.
- `CheckoutStep` and `CheckoutSurface` hold the known step and surface names; responses keep them as strings, so a new step does not break this version.
- Needs the API with the payment page funnel (November 2026).

## 1.11.0

- Invoices priced in another currency: `CreateInvoice::inCurrency($amount, $currency, ...)` with the amount in the smallest unit of the currency (4999 is 49.99 EUR), or `new CreateInvoice(null, ..., amount: 4999, currency: 'EUR')`. Give `amountUsdCents` or `amount` with `currency`, never both; `$amountUsdCents` is now nullable for that. The API converts the price once, at creation, rounded up to the cent, and keeps the rate.
- `Invoice::$pricing` (`InvoicePricing`): the currency, amount, exponent, `usdPerUnit`, `rateSource` (`ecb` or `merchant`) and `rateAt` of an invoice priced in another currency; null for a USD invoice and on webhook events recorded before 19 November 2026.
- `$client->currencies()->list()`: every supported currency (`Currency`) with its exponent and the market rate in use (`MarketRate`). `$client->currencies()->forStore($storeId)`: the same with the store's own fixed rates (`StoreRate`) and the rate an invoice of the store would use now (`CurrencyRate`). A store's own rate is set in the dashboard only.
- New `ApiException::EXCHANGE_RATE_UNAVAILABLE` and `ApiException::CURRENCY_NOT_SUPPORTED`.
- `Client` takes `Currencies` as a fifth constructor argument; `Client::create()`, `Client::fromPsr()` and `Client::fromTransport()` are unchanged.

## 1.10.0

- `Store::$webhookHealth` (`WebhookHealth`): the state of the store's webhook endpoint, null when the store has no webhook URL. `status` is `WebhookHealth::ACTIVE`, `WebhookHealth::FAILING` (at least 10 failed attempts, `pausesAt` says when it will be paused if it keeps failing) or `WebhookHealth::PAUSED` (failed for 24 hours; new events wait up to 30 days); also `failingSince`, `failedAttempts`, `pausedAt` and `pauseReason`. The merchant resumes a paused endpoint in the dashboard. Needs the API with paused endpoints (November 2026); an older answer without the field is refused as an unexpected response.
- `UnmatchedTransfer::$sharedWithStores` is documented as it is counted: the active stores that offer the network and pay out to the same wallet.

## 1.9.0

- A store can pay out to its own address instead of the account one (set in the dashboard). `Store::$payoutAddresses` are now the addresses this store's new invoices use; `Store::$payoutSources` (`PayoutSources`) says for each family whether it is the store's own (`PayoutSources::STORE`) or the account's (`PayoutSources::ACCOUNT`); `Store::$accountPayoutAddresses` lists the account addresses. `$evmPayoutStatus` and `$evmNetworksBlocked` follow the EVM address the store uses.
- `Store::$archivedAt`: when the store was archived, null while it is not.
- The webhook types `api_key.leaked` and `webhook_secret.leaked` are documented, and `store.payout_attention` carries `payout.source`.
- These need the API with store payout addresses (November 2026); an older answer without the new store fields is refused as an unexpected response.

## 1.8.0

- Stablevoy is moving to its own key formats: API keys `svk_live_...` / `svk_test_...` and webhook signing secrets `svwhsec_...`, each ending in a checksum. The SDK accepts them and the older `sk_live_` / `sk_test_` / `whsec_` in connect answers; older keys keep working.
- `KeyFormat::checkApiKey($value)` and `KeyFormat::checkWebhookSecret($value)` return a `KeyCheck`: `Ok`, `Mistyped` (a new-format key whose checksum does not match, so a character is wrong or missing) or `NotAKey`.

## 1.7.0

- `Invoices::summary(period: ...)` or `Invoices::summary(createdFrom: ..., createdTo: ...)`: totals of the invoices created in a range of UTC days (`InvoiceSummary`), with the count per status, the invoiced amount and the money received, also per network and token (`NetworkTotal`). Periods are in `SummaryPeriod`; one request covers at most 92 days, and a longer range is refused with `ApiException::INVALID_INVOICE_FILTER`.
- `Client::unmatchedTransfers()->list()`: transfers to the payout wallet that matched no invoice, on the networks of the key's store (`UnmatchedTransfer`), each with `sharedWithStores`. Read only; they are attached to an invoice in the dashboard.
- `Limits` carries `apiKeysPerStore` (`ApiKeysPerStore` with `plugin`, `mcp`, `api`), `openInvoicesPerStore` and `invoicesPerMinutePerStore`. New `ApiException::OPEN_INVOICE_LIMIT_REACHED`. These need the API of 7 November 2026; an older answer without them is refused as an unexpected response.
- The `Client` constructor takes `UnmatchedTransfers` as a fourth argument; `Client::create()`, `fromPsr()` and `fromTransport()` are unchanged.

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
