<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Store;

use Override;
use Tilttek\PlayLog\Contract\PlayStore;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Exception\PlayConflict;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayQuery;

/**
 * The plays in memory, for tests: they last as long as the object.
 */
final class InMemoryPlayStore implements PlayStore
{
    /** @var array<string, Play> by identifier */
    private array $plays = [];

    #[Override]
    public function play(string $id): ?Play
    {
        return $this->plays[$id] ?? null;
    }

    #[Override]
    public function save(Play $play): Play
    {
        if ($play->version !== ($this->plays[$play->id]->version ?? 0)) {
            throw PlayConflict::changed($play->id);
        }

        return $this->plays[$play->id] = $play->withVersion($play->version + 1);
    }

    #[Override]
    public function delete(Play $play): void
    {
        $kept = $this->plays[$play->id] ?? null;

        if (!$kept instanceof Play || $play->version !== $kept->version) {
            throw PlayConflict::changed($play->id);
        }

        unset($this->plays[$play->id]);
    }

    #[Override]
    public function plays(PlayQuery $query, int $limit): iterable
    {
        if ($limit < 1) {
            throw InvalidPlay::because('A store reads at least one play at once.');
        }

        $plays = array_filter($this->plays, $query->matches(...));
        usort($plays, $this->order(...));

        return \array_slice($plays, 0, $limit);
    }

    private function order(Play $a, Play $b): int
    {
        // The latest first; PHP puts a null date, which compares as false, after any date.
        return [$b->playedAt, $a->id] <=> [$a->playedAt, $b->id];
    }
}
