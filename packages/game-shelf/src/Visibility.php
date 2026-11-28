<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * Who sees a part of a shelf besides its member: nobody, the signed-in members, or anyone.
 */
enum Visibility: string
{
    case Private = 'private';
    case Members = 'members';
    case Public = 'public';

    public function shows(Viewer $viewer): bool
    {
        return match ($viewer) {
            Viewer::Owner => true,
            Viewer::Member => self::Private !== $this,
            Viewer::Anyone => self::Public === $this,
        };
    }
}
