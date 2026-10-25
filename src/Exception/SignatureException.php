<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** A webhook did not pass the signature check; the message says why. Answer such a request with HTTP 400. */
final class SignatureException extends \RuntimeException implements StablevoyException {}
