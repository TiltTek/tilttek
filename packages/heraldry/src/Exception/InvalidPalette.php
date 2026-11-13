<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Exception;

use InvalidArgumentException;

/**
 * A palette that names a tincture other than a metal or a colour, or a colour not written `#rrggbb`.
 */
final class InvalidPalette extends InvalidArgumentException implements HeraldryException
{
}
