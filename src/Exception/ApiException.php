<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/**
 * The API answered with an error: an RFC 7807 problem document with a stable code, a readable detail and a link to
 * the code's entry in the error catalog (https://stablevoy.com/docs/api/errors/).
 */
class ApiException extends \RuntimeException implements StablevoyException
{
    /**
     * @param list<array{path: string, message: string}> $issues the fields a validation error names
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        public readonly string $detail,
        public readonly ?string $type,
        public readonly array $issues,
        public readonly ?int $retryAfterSeconds,
    ) {
        parent::__construct(sprintf('%s (HTTP %d, %s)', $detail, $status, $errorCode), $status);
    }
}
