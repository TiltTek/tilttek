<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;

/**
 * The access of an application that has not said who runs its games: nobody does, so the console, the hint screen
 * and the consent are refused until the application registers its own `EscapeRoomAccess`.
 */
final readonly class NoEscapeRoomAccess implements EscapeRoomAccess
{
    #[Override]
    public function host(StoredGame $game): ?ResourceReference
    {
        return null;
    }

    #[Override]
    public function watches(StoredGame $game): bool
    {
        return false;
    }

    #[Override]
    public function speaksForTeam(StoredGame $game): bool
    {
        return false;
    }
}
