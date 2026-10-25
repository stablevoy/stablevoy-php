<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** The request was refused because of its content (HTTP 400 or 422); issues names the fields. */
final class ValidationException extends ApiException {}
