<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Stablevoy\Exception\ApiException;
use Stablevoy\Exception\AuthenticationException;
use Stablevoy\Exception\MaintenanceException;
use Stablevoy\Exception\NotFoundException;
use Stablevoy\Exception\RateLimitedException;
use Stablevoy\Exception\ValidationException;

/** Turns an error answer of the API (an RFC 7807 problem document) into the matching exception. */
final class ErrorFactory
{
    public function fromProblem(int $status, string $body, ?int $retryAfterSeconds): ApiException
    {
        $problem = json_decode($body, true);
        $problem = is_array($problem) ? $problem : [];
        $code = is_string($problem['code'] ?? null) ? $problem['code'] : 'http_' . $status;
        $detail = is_string($problem['detail'] ?? null) ? $problem['detail'] : sprintf('The API answered HTTP %d', $status);
        $type = is_string($problem['type'] ?? null) ? $problem['type'] : null;
        $issues = self::issues($problem['issues'] ?? []);
        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 404 => NotFoundException::class,
            $status === 429 => RateLimitedException::class,
            $status === 503 && $code === 'maintenance' => MaintenanceException::class,
            $status === 400 || $status === 422 => ValidationException::class,
            default => ApiException::class,
        };
        return new $class($status, $code, $detail, $type, $issues, $retryAfterSeconds);
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    private static function issues(mixed $issues): array
    {
        if (!is_array($issues)) {
            return [];
        }
        $out = [];
        foreach ($issues as $issue) {
            if (is_array($issue) && is_string($issue['path'] ?? null) && is_string($issue['message'] ?? null)) {
                $out[] = ['path' => $issue['path'], 'message' => $issue['message']];
            }
        }
        return $out;
    }
}
