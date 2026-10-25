<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** The client cannot be built as asked, for example no PSR-18 HTTP client is installed. */
final class ConfigurationException extends \LogicException implements StablevoyException {}
