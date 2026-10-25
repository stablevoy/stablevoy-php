<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\Fields;

final class FieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>, \Closure(Fields): mixed, string}>
     */
    public static function wrong(): iterable
    {
        yield 'missing' => [['a' => 1], static fn(Fields $f): mixed => $f->string('b'), 'x.b is missing'];
        yield 'string' => [['a' => 1], static fn(Fields $f): mixed => $f->string('a'), 'x.a is not a string'];
        yield 'nullable string' => [['a' => 1], static fn(Fields $f): mixed => $f->nullableString('a'), 'x.a is not a string or null'];
        yield 'int' => [['a' => '1'], static fn(Fields $f): mixed => $f->int('a'), 'x.a is not an integer'];
        yield 'nullable int' => [['a' => 1.5], static fn(Fields $f): mixed => $f->nullableInt('a'), 'x.a is not an integer or null'];
        yield 'bool' => [['a' => 1], static fn(Fields $f): mixed => $f->bool('a'), 'x.a is not a boolean'];
        yield 'date' => [['a' => 'yesterday-ish'], static fn(Fields $f): mixed => $f->date('a'), 'x.a is not an ISO 8601 date'];
        yield 'list' => [['a' => ['k' => 1]], static fn(Fields $f): mixed => $f->list('a'), 'x.a is not a list'];
        yield 'strings' => [['a' => [1]], static fn(Fields $f): mixed => $f->strings('a'), 'x.a is not a list of strings'];
        yield 'object' => [['a' => [1, 2]], static fn(Fields $f): mixed => $f->object('a'), 'x.a is not a JSON object'];
    }

    /**
     * @param array<mixed> $data
     * @param \Closure(Fields): mixed $read
     */
    #[DataProvider('wrong')]
    public function testNamesTheFieldThatIsMissingOrWrong(array $data, \Closure $read, string $message): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);
        $read(Fields::of($data, 'x'));
    }

    public function testReadsNullsAndDates(): void
    {
        $f = Fields::of(['s' => null, 'i' => null, 'd' => null, 'e' => '2026-10-25T10:00:00.000Z'], 'x');
        self::assertNull($f->nullableString('s'));
        self::assertNull($f->nullableInt('i'));
        self::assertNull($f->nullableDate('d'));
        self::assertSame('2026-10-25', $f->nullableDate('e')?->format('Y-m-d'));
    }

    public function testRefusesAListWhereAnObjectBelongs(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        Fields::of([1, 2], 'x');
    }
}
