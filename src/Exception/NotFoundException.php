<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** The invoice or store does not exist for this API key (HTTP 404). */
final class NotFoundException extends ApiException {}
