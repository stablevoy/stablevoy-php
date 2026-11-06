<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Http\Transport;
use Stablevoy\Model\CreatedInvoice;
use Stablevoy\Model\CreateInvoice;
use Stablevoy\Model\Fields;
use Stablevoy\Model\Invoice;
use Stablevoy\Model\InvoiceFilter;
use Stablevoy\Model\InvoicePage;

/** Invoices of the store the API key belongs to. */
final class Invoices
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * Creates an invoice. Pass your order id as the idempotency key: a retry with the same key and request returns the
     * first invoice (replayed: true) instead of a second one, and only then does this library retry on its own.
     */
    public function create(CreateInvoice $request, ?string $idempotencyKey = null): CreatedInvoice
    {
        // What the API accepts; anything else would be refused there, or break the header on the way.
        if ($idempotencyKey !== null && preg_match('/^[\x21-\x7e]{1,255}$/', $idempotencyKey) !== 1) {
            throw new \InvalidArgumentException('The idempotency key must be 1 to 255 printable ASCII characters without spaces, such as your order id');
        }
        return CreatedInvoice::fromFields(Fields::of($this->transport->post('/v1/invoices', $request->toArray(), $idempotencyKey), 'invoice'));
    }

    public function get(string $invoiceId): Invoice
    {
        self::checkId($invoiceId);
        return Invoice::fromFields(Fields::of($this->transport->get('/v1/invoices/' . $invoiceId), 'invoice'));
    }

    /**
     * Cancels an invoice that is still waiting for its first payment, for example when the order was cancelled: the
     * payment page tells the buyer not to pay and the webhook invoice.cancelled is sent. Cancelling it again returns it
     * unchanged. Once a payment has been seen the API refuses with ApiException::INVOICE_NOT_CANCELLABLE; money that still
     * arrives after a cancel makes the invoice paid_late. Not retried on its own; calling it again is safe.
     */
    public function cancel(string $invoiceId): Invoice
    {
        self::checkId($invoiceId);
        return Invoice::fromFields(Fields::of($this->transport->post('/v1/invoices/' . $invoiceId . '/cancel', []), 'invoice'));
    }

    /**
     * One page of invoices, newest first.
     *
     * @param int $limit 1 to 100
     * @param string|null $status only invoices in this status (see InvoiceStatus); or use $filter
     * @param InvoiceFilter|null $filter status, source, created days and search
     */
    public function list(int $limit = 50, ?string $cursor = null, ?string $status = null, ?InvoiceFilter $filter = null): InvoicePage
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('limit must be between 1 and 100');
        }
        if ($status !== null && $filter?->status !== null) {
            throw new \InvalidArgumentException('Pass the status either as $status or in the filter, not both');
        }
        $query = ['limit' => $limit];
        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }
        if ($status !== null) {
            $query['status'] = $status;
        }
        $query += $filter?->toQuery() ?? [];
        return InvoicePage::fromFields(Fields::of($this->transport->get('/v1/invoices', $query), 'invoices'));
    }

    /**
     * Every invoice, newest first, fetching the pages as you iterate.
     *
     * @return \Generator<int, Invoice>
     */
    public function all(?string $status = null, int $pageSize = 100, ?InvoiceFilter $filter = null): \Generator
    {
        $cursor = null;
        do {
            $page = $this->list($pageSize, $cursor, $status, $filter);
            yield from $page->items;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    // Ids are UUIDs; anything else (such as "..") could make the request reach another path with your key.
    private static function checkId(string $invoiceId): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invoiceId) !== 1) {
            throw new \InvalidArgumentException('An invoice id is a UUID, like 7c3360b5-e8cc-4775-8a13-7324d4f1296e');
        }
    }
}
