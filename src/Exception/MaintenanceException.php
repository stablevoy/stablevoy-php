<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** Stablevoy is under maintenance (HTTP 503, code maintenance). Nothing was changed; retry after retryAfterSeconds. */
final class MaintenanceException extends ApiException {}
