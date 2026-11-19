<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Jail;

/**
 * What happened to a die in the jail's history.
 */
enum EventKind: string
{
    /** The die went to jail, for a sentence or until released. */
    case Jailed = 'jailed';

    /** The die was released before its sentence ended, or from a sentence without end. */
    case Released = 'released';
}
