<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** The API key is missing, invalid or revoked (HTTP 401). */
final class AuthenticationException extends ApiException {}
