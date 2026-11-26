<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Store;

use Tilttek\CardDeck\Contract\GameStore;
use Tilttek\CardDeck\Exception\ConcurrentChange;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\GameRecord;

/**
 * A store in memory, for tests: it keeps its games for the life of the object, which a long-running worker must never
 * share between requests.
 */
final class InMemoryGameStore implements GameStore
{
    /** @var array<string, GameRecord> */
    private array $records = [];

    public function find(GameId $id): ?GameRecord
    {
        return $this->records[$id->value] ?? null;
    }

    public function save(GameRecord $record): void
    {
        $stored = $this->records[$record->id->value] ?? null;

        if (($stored->version ?? -1) !== $record->version - 1) {
            throw ConcurrentChange::because(\sprintf('The game "%s" changed since version %d was read.', $record->id->value, $record->version - 1));
        }

        $this->records[$record->id->value] = $record;
    }
}
