<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** Too many requests (HTTP 429); retryAfterSeconds says when to try again. */
final class RateLimitedException extends ApiException {}
