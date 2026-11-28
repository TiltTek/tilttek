<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Store;

use Override;
use Tilttek\GameNight\Contract\NightStore;
use Tilttek\GameNight\Exception\NightConflict;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\GameNight;

/**
 * The nights in memory, for tests: they last as long as the object.
 */
final class InMemoryNightStore implements NightStore
{
    /** @var array<string, GameNight> by night */
    private array $nights = [];

    #[Override]
    public function night(string $id): GameNight
    {
        return $this->nights[$id] ?? throw NightNotFound::withId($id);
    }

    #[Override]
    public function save(GameNight $night): GameNight
    {
        if ($night->version !== ($this->nights[$night->id]->version ?? 0)) {
            throw NightConflict::changed($night->id);
        }

        return $this->nights[$night->id] = $night->withVersion($night->version + 1);
    }
}
