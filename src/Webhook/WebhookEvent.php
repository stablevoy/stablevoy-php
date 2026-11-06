<?php

declare(strict_types=1);

namespace Stablevoy\Webhook;

use Stablevoy\Model\Fields;
use Stablevoy\Model\Invoice;

/**
 * A verified webhook. Event types: invoice.paid, invoice.overpaid, invoice.underpaid, invoice.expired,
 * invoice.paid_late, invoice.on_hold, invoice.cancelled, invoice.payment_flagged, store.payout_attention and webhook.test.
 * invoice.payment_flagged: a payment of a settled invoice was flagged by screening; the invoice keeps its status and the
 * event data holds the payment under 'payment'. store.payout_attention: the EVM payout wallet of the account became
 * unconfirmed or is fine again; $invoice is null and $data['payout'] holds status, blockedNetworks and dashboardUrl.
 * Re-read Stores::current() for the current state. Deliveries can repeat: use $id to process each event once.
 */
final class WebhookEvent
{
    /**
     * @param array<mixed> $data the event data as sent
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?Invoice $invoice,
        public readonly array $data,
    ) {}

    public static function fromFields(Fields $f): self
    {
        $type = $f->string('type');
        $data = $f->object('data');
        return new self(
            $f->string('id'),
            $type,
            $f->date('createdAt'),
            str_starts_with($type, 'invoice.') ? Invoice::fromFields($data->object('invoice')) : null,
            $data->raw(),
        );
    }
}
