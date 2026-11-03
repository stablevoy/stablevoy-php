<?php

declare(strict_types=1);

namespace Stablevoy\Http;

/**
 * Collects the header lines curl hands to CURLOPT_HEADERFUNCTION. curl reports every answer it reads, so a proxy's
 * CONNECT answer and 1xx answers come before the real one; each status line starts over, and only the headers of the
 * final answer are kept.
 *
 * @internal
 */
final class CurlHeaderLines
{
    /** @var list<array{string, string}> */
    private array $headers = [];

    /** Takes one line as curl gives it and returns its length, as curl expects. */
    public function add(string $line): int
    {
        if (str_starts_with($line, 'HTTP/')) {
            $this->headers = [];
            return strlen($line);
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $this->headers[] = [trim($parts[0]), trim($parts[1])];
        }
        return strlen($line);
    }

    /**
     * @return list<array{string, string}> name and value of each header of the final answer, in order
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
