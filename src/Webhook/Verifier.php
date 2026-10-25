<?php

declare(strict_types=1);

namespace Stablevoy\Webhook;

use Stablevoy\Exception\SignatureException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\Fields;
use Stablevoy\Time\Clock;
use Stablevoy\Time\SystemClock;

/**
 * Checks the X-Stablevoy-Signature header of a webhook (t=<unix>,v1=<hex hmac-sha256(secret, t + "." + raw body)>)
 * and returns the event. Pass the raw request body exactly as received, before any JSON decoding.
 */
final class Verifier
{
    /**
     * @param string $secret the webhook signing secret of the store (whsec_...)
     * @param int $toleranceSeconds how old a signature may be, against replays
     */
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300,
        private readonly Clock $clock = new SystemClock(),
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('The webhook signing secret is empty. It is shown once when you set the webhook URL in the dashboard.');
        }
        if ($toleranceSeconds < 1) {
            throw new \InvalidArgumentException('toleranceSeconds must be at least 1');
        }
    }

    public function verify(string $rawBody, string $signatureHeader): WebhookEvent
    {
        [$timestamp, $signatures] = self::parse($signatureHeader);
        if (abs($this->clock->now() - $timestamp) > $this->toleranceSeconds) {
            throw new SignatureException('The webhook signature is too old or from the future; check the server clock');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secret);
        $matches = array_filter($signatures, static fn(string $signature): bool => hash_equals($expected, $signature));
        if ($matches === []) {
            throw new SignatureException('The webhook signature does not match: wrong secret, or the body was changed');
        }
        try {
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            return WebhookEvent::fromFields(Fields::of($event, 'event'));
        } catch (\JsonException | UnexpectedResponseException $e) {
            throw new SignatureException('The webhook is signed but its body is not a Stablevoy event: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{int, list<string>} the timestamp and every v1 signature in the header
     */
    private static function parse(string $header): array
    {
        if (trim($header) === '') {
            throw new SignatureException('The X-Stablevoy-Signature header is missing');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === []) {
            throw new SignatureException('The X-Stablevoy-Signature header is malformed');
        }
        return [$timestamp, $signatures];
    }
}
