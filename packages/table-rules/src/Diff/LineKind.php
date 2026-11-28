<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Diff;

/**
 * What became of a line of a rule's text.
 */
enum LineKind: string
{
    case Kept = 'kept';
    case Removed = 'removed';
    case Added = 'added';
}
