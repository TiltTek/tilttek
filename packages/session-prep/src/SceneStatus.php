<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

/**
 * Where a scene stands: planned, played at the table, or skipped because the players went elsewhere.
 */
enum SceneStatus: string
{
    case Planned = 'planned';
    case Played = 'played';
    case Skipped = 'skipped';
}
