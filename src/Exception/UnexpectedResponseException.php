<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** The API answered something this library cannot read: not JSON, or a field missing or of the wrong type. */
final class UnexpectedResponseException extends \RuntimeException implements StablevoyException {}
