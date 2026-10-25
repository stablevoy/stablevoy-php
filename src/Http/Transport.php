<?php

declare(strict_types=1);

namespace Stablevoy\Http;

/**
 * Sends one API call and returns the decoded JSON answer, or throws the matching exception. The resources depend on
 * this interface only.
 */
interface Transport
{
    /**
     * @param array<string, string|int> $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function post(string $path, array $body, ?string $idempotencyKey = null): array;
}
