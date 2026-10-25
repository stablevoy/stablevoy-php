<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\TestCase;
use Stablevoy\Exception\SignatureException;
use Stablevoy\Tests\Support\FixedClock;
use Stablevoy\Webhook\Verifier;

/** The vectors in tests/Fixtures/webhook-vectors.json were signed by the API's own TypeScript signer. */
final class WebhookVerifierTest extends TestCase
{
    /** @var array{secret: string, cases: list<array{name: string, timestamp: int, body: string, header: string}>} */
    private array $vectors;

    protected function setUp(): void
    {
        /** @var array{secret: string, cases: list<array{name: string, timestamp: int, body: string, header: string}>} $vectors */
        $vectors = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/webhook-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->vectors = $vectors;
    }

    public function testAcceptsWhatTheApiSigned(): void
    {
        foreach ($this->vectors['cases'] as $case) {
            $verifier = new Verifier($this->vectors['secret'], 300, new FixedClock($case['timestamp'] + 10));
            $event = $verifier->verify($case['body'], $case['header']);
            $body = json_decode($case['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            self::assertSame($body['id'], $event->id, $case['name']);
            self::assertSame($body['type'], $event->type, $case['name']);
        }
    }

    public function testGivesTheInvoiceOfAnInvoiceEventAndTheDataOfOthers(): void
    {
        [$paid, $test] = $this->vectors['cases'];
        $verifier = new Verifier($this->vectors['secret'], 300, new FixedClock($paid['timestamp']));
        $event = $verifier->verify($paid['body'], $paid['header']);
        self::assertNotNull($event->invoice);
        self::assertSame('2026-10-25T10:00:00+00:00', $event->createdAt->format(DATE_ATOM));

        $verifier = new Verifier($this->vectors['secret'], 300, new FixedClock($test['timestamp']));
        $event = $verifier->verify($test['body'], $test['header']);
        self::assertNull($event->invoice);
        self::assertSame('Test event from Stablevoy, café', $event->data['message']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function tampered(): iterable
    {
        yield 'changed body' => ['body', '', 'does not match'];
        yield 'wrong secret' => ['secret', '', 'does not match'];
        yield 'missing header' => ['header', '', 'header is missing'];
        yield 'no timestamp' => ['header', 'v1=abc', 'malformed'];
        yield 'no signature' => ['header', 't=1792900000', 'malformed'];
        yield 'too old' => ['clock', '301', 'too old'];
        yield 'from the future' => ['clock', '-301', 'too old'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tampered')]
    public function testRefusesWhatWasNotSignedByStablevoy(string $what, string $value, string $reason): void
    {
        $case = $this->vectors['cases'][0];
        $body = $what === 'body' ? str_replace('paid', 'pa1d', $case['body']) : $case['body'];
        $header = $what === 'header' ? $value : $case['header'];
        $secret = $what === 'secret' ? 'whsec_other' : $this->vectors['secret'];
        $now = $case['timestamp'] + ($what === 'clock' ? (int) $value : 0);
        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage($reason);
        (new Verifier($secret, 300, new FixedClock($now)))->verify($body, $header);
    }

    public function testAcceptsAnyOfSeveralSignatures(): void
    {
        $case = $this->vectors['cases'][0];
        $header = str_replace(',v1=', ',v1=' . str_repeat('0', 64) . ',v1=', $case['header']);
        $event = (new Verifier($this->vectors['secret'], 300, new FixedClock($case['timestamp'])))->verify($case['body'], $header);
        self::assertSame('invoice.paid', $event->type);
    }

    public function testRefusesASignedBodyThatIsNoEvent(): void
    {
        $secret = $this->vectors['secret'];
        $body = '{"hello":"world"}';
        $header = 't=1000,v1=' . hash_hmac('sha256', '1000.' . $body, $secret);
        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('is not a Stablevoy event');
        (new Verifier($secret, 300, new FixedClock(1000)))->verify($body, $header);
    }

    public function testNeedsASecretAndAPositiveTolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Verifier('');
    }

    public function testNeedsAPositiveTolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Verifier('whsec_x', 0);
    }
}
