<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

/** The response examples of the API's own OpenAPI document (tests/Fixtures/openapi.json, refreshed by bin/refresh-openapi). */
final class OpenApi
{
    /** @var array<mixed>|null */
    private static ?array $document = null;

    /**
     * @return array<mixed>
     */
    public static function example(string $method, string $path, int $status): array
    {
        $node = self::document();
        foreach (['paths', $path, strtolower($method), 'responses', (string) $status, 'content', 'application/json', 'example'] as $key) {
            if (!is_array($node) || !array_key_exists($key, $node)) {
                throw new \LogicException(sprintf('No example for %s %s %d in the OpenAPI fixture (missing %s)', $method, $path, $status, $key));
            }
            $node = $node[$key];
        }
        if (!is_array($node)) {
            throw new \LogicException(sprintf('The example for %s %s %d is not an object', $method, $path, $status));
        }
        return $node;
    }

    /**
     * @return array<mixed>
     */
    public static function document(): array
    {
        if (self::$document === null) {
            $decoded = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \LogicException('The OpenAPI fixture is not an object');
            }
            self::$document = $decoded;
        }
        return self::$document;
    }
}
