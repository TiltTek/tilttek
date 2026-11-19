<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Screen;

/**
 * What the hint screen of each game shows, one screen per game.
 */
interface ScreenStore
{
    public function show(string $game, Screen $screen): void;

    public function screen(string $game): ?Screen;

    /**
     * @return array<string, Screen> the screens a host showed last, by game
     */
    public function ofHost(ResourceReference $host): array;

    /**
     * Writes the pseudonym in place of the host on each screen they showed last.
     *
     * @return int the screens changed
     */
    public function eraseHost(ResourceReference $host, ResourceReference $pseudonym): int;
}
