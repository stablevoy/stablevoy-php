<?php

declare(strict_types=1);

namespace Stablevoy\Key;

/** How a pasted or received key or secret looks. */
enum KeyCheck: string
{
    case Ok = 'ok';
    /** A new-format key or secret whose checksum does not match: a character is wrong or missing. */
    case Mistyped = 'mistyped';
    case NotAKey = 'not_a_key';
}
