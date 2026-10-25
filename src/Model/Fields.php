<?php

declare(strict_types=1);

namespace Stablevoy\Model;

use Stablevoy\Exception\UnexpectedResponseException;

/**
 * Strict reads from a decoded JSON object. A missing field or one of the wrong type is an error naming the field,
 * never a silent default.
 *
 * @internal
 */
final class Fields
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $path,
    ) {}

    /**
     * @param mixed $value a decoded JSON value expected to be an object
     */
    public static function of(mixed $value, string $path): self
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new UnexpectedResponseException(sprintf('%s is not a JSON object', $path));
        }
        return new self($value, $path);
    }

    public function string(string $key): string
    {
        $value = $this->value($key);
        if (!is_string($value)) {
            throw $this->wrong($key, 'a string');
        }
        return $value;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->value($key);
        if ($value !== null && !is_string($value)) {
            throw $this->wrong($key, 'a string or null');
        }
        return $value;
    }

    public function int(string $key): int
    {
        $value = $this->value($key);
        if (!is_int($value)) {
            throw $this->wrong($key, 'an integer');
        }
        return $value;
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->value($key);
        if ($value !== null && !is_int($value)) {
            throw $this->wrong($key, 'an integer or null');
        }
        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->value($key);
        if (!is_bool($value)) {
            throw $this->wrong($key, 'a boolean');
        }
        return $value;
    }

    public function date(string $key): \DateTimeImmutable
    {
        return $this->parseDate($key, $this->string($key));
    }

    public function nullableDate(string $key): ?\DateTimeImmutable
    {
        $value = $this->nullableString($key);
        return $value === null ? null : $this->parseDate($key, $value);
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_map(
            function (mixed $item) use ($key): string {
                if (!is_string($item)) {
                    throw $this->wrong($key, 'a list of strings');
                }
                return $item;
            },
            $this->list($key),
        );
    }

    /**
     * @return list<mixed>
     */
    public function list(string $key): array
    {
        $value = $this->value($key);
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->wrong($key, 'a list');
        }
        return $value;
    }

    public function object(string $key): self
    {
        return self::of($this->value($key), $this->path . '.' . $key);
    }

    /**
     * @return array<mixed> the object as decoded, for data this library does not model
     */
    public function raw(): array
    {
        return $this->data;
    }

    public function path(string $key): string
    {
        return $this->path . '.' . $key;
    }

    private function value(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw new UnexpectedResponseException(sprintf('%s.%s is missing', $this->path, $key));
        }
        return $this->data[$key];
    }

    private function parseDate(string $key, string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw $this->wrong($key, 'an ISO 8601 date');
        }
    }

    private function wrong(string $key, string $expected): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf('%s.%s is not %s', $this->path, $key, $expected));
    }
}
