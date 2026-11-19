<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\StoredGame;

/**
 * The access a test sets: who hosts, whether the viewer watches the screens and speaks for the team.
 */
final class Access implements EscapeRoomAccess
{
    public ?ResourceReference $host = null;

    public bool $watching = false;

    public bool $team = false;

    #[Override]
    public function host(StoredGame $game): ?ResourceReference
    {
        return $this->host;
    }

    #[Override]
    public function watches(StoredGame $game): bool
    {
        return $this->watching;
    }

    #[Override]
    public function speaksForTeam(StoredGame $game): bool
    {
        return $this->team;
    }
}
