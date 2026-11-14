<?php

declare(strict_types=1);

namespace Tilttek\Rating;

/**
 * Who sees a member's rating of a game. A rating is private until the member makes it public: only a public rating
 * appears in the standings.
 */
enum Visibility: string
{
    /** Seen by the member alone; the default. */
    case Private = 'private';

    /** Seen in the standings of the game, by the member's own choice. */
    case Public = 'public';
}
