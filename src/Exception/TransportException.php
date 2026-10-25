<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** No answer from the API: the connection failed or timed out. */
final class TransportException extends \RuntimeException implements StablevoyException {}
