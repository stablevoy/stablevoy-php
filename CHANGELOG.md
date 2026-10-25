# Changelog

## 1.0.1

Security hardening:

- The base URL must be https and only a host (an optional port is fine); plain http is accepted only for localhost, and URLs with credentials, a path or a query are refused, so the API key is never sent unencrypted or to an unexpected place.
- Invoice ids must be UUIDs. Before, an id such as `..` produced a request to another path of the API with your key.
- CI actions are pinned to commits.

## 1.0.0

First release: invoices (create with an idempotency key, read, list and walk all pages), the store of the API key, limits, pricing and networks, typed errors with retries where a repeat is safe, and webhook signature verification.
