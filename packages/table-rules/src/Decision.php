<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

/**
 * What a player said of a version of the rules.
 */
enum Decision: string
{
    case Accepted = 'accepted';
    case Declined = 'declined';
}
