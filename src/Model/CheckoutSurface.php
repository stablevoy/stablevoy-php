<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** Where the payment page ran when it was opened: the hosted page or the widget on the store's site. */
final class CheckoutSurface
{
    public const PAGE = 'page';
    public const WIDGET = 'widget';
}
