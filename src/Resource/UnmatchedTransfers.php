<?php

declare(strict_types=1);

namespace Stablevoy\Resource;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Http\Transport;
use Stablevoy\Model\Fields;
use Stablevoy\Model\UnmatchedTransfer;

/** Transfers to the payout wallet that matched no invoice, on the networks of the key's store. Read only. */
final class UnmatchedTransfers
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * Newest first, at most 100. The API allows 30 calls a minute per store.
     *
     * @return list<UnmatchedTransfer>
     */
    public function list(): array
    {
        $data = $this->transport->get('/v1/unmatched-transfers');
        if (!array_is_list($data)) {
            throw new UnexpectedResponseException('unmatchedTransfers is not a list');
        }
        return array_map(static fn(mixed $item, int $i): UnmatchedTransfer => UnmatchedTransfer::fromFields(Fields::of($item, "unmatchedTransfers[$i]")), $data, array_keys($data));
    }
}
