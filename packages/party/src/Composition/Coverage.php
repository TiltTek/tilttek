<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

/**
 * How well a role is filled: below its minimum, filled, or by more members than its fair share.
 */
enum Coverage: string
{
    case Missing = 'missing';

    case Covered = 'covered';

    case Crowded = 'crowded';
}
