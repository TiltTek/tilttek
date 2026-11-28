<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit\Fixture;

use Generator;
use Override;
use Tilttek\PlayLog\Contract\PlayStore;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\Store\InMemoryPlayStore;

/**
 * A store that reads lazily, every play under the same key, and keeps the order of its reads and writes.
 */
final class LazyStore implements PlayStore
{
    /** @var list<string> */
    public array $journal = [];

    private readonly InMemoryPlayStore $inner;

    public function __construct()
    {
        $this->inner = new InMemoryPlayStore();
    }

    #[Override]
    public function play(string $id): ?Play
    {
        return $this->inner->play($id);
    }

    #[Override]
    public function save(Play $play): Play
    {
        $this->journal[] = 'save '.$play->id;

        return $this->inner->save($play);
    }

    #[Override]
    public function delete(Play $play): void
    {
        $this->journal[] = 'delete '.$play->id;
        $this->inner->delete($play);
    }

    /**
     * @return Generator<string, Play>
     */
    #[Override]
    public function plays(PlayQuery $query, int $limit): Generator
    {
        foreach ($this->inner->plays($query, $limit) as $play) {
            $this->journal[] = 'read '.$play->id;

            yield 'same' => $play;
        }
    }
}
