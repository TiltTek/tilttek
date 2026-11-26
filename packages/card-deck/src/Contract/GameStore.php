<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Contract;

use Tilttek\CardDeck\Exception\ConcurrentChange;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\GameRecord;

/**
 * The store of the games: one record by game, written only over the version before it. An adapter keeps the seed of a
 * game unfinished as a secret — encrypted at rest, never sent to a browser.
 */
interface GameStore
{
    public function find(GameId $id): ?GameRecord;

    /**
     * Writes the record when the stored one is its previous version — none for a record of version 0.
     *
     * @throws ConcurrentChange when another version was stored in between
     */
    public function save(GameRecord $record): void;
}
