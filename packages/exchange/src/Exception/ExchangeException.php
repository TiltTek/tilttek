<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use Throwable;

/**
 * Implemented by every exception of the library, to catch them as one.
 */
interface ExchangeException extends Throwable
{
}
