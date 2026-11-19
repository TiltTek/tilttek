<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Exception;

use Throwable;

/**
 * Every exception of the library: catch this one to catch them all. The exceptions of `tilttek/puzzle-builder` pass
 * through unchanged.
 */
interface EscapeRoomException extends Throwable
{
}
