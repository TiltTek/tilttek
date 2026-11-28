<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * Who looks at a shelf: its member, another signed-in member, or anyone. The application decides which one a visitor
 * is.
 */
enum Viewer: string
{
    case Owner = 'owner';
    case Member = 'member';
    case Anyone = 'anyone';
}
