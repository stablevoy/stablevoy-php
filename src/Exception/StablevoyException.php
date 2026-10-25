<?php

declare(strict_types=1);

namespace Stablevoy\Exception;

/** Every exception this library throws implements it, so one catch covers them all. */
interface StablevoyException extends \Throwable {}
