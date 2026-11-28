<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Contract;

use Tilttek\GameNight\Exception\NightConflict;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\GameNight;

/**
 * Keeps the nights, each at a version.
 */
interface NightStore
{
    /**
     * @throws NightNotFound when no night has the identifier
     */
    public function night(string $id): GameNight;

    /**
     * Keeps the night at the next version, and returns it. A night at version 0 is new; any other is kept only when
     * the store holds it at that version.
     *
     * @throws NightConflict when the night is new and the store has one of its identifier, or the store holds it at
     *                       another version; nothing is kept
     */
    public function save(GameNight $night): GameNight;
}
