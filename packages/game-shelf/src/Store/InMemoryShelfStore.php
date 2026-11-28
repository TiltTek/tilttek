<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Store;

use Override;
use Tilttek\GameShelf\Contract\ShelfStore;
use Tilttek\GameShelf\Exception\ShelfConflict;
use Tilttek\GameShelf\Shelf;

/**
 * The shelves in memory, for tests: they last as long as the object.
 */
final class InMemoryShelfStore implements ShelfStore
{
    /** @var array<string, Shelf> by member */
    private array $shelves = [];

    #[Override]
    public function shelf(string $member): Shelf
    {
        return $this->shelves[$member] ?? new Shelf($member);
    }

    #[Override]
    public function save(Shelf $shelf): Shelf
    {
        if ($shelf->version !== ($this->shelves[$shelf->member]->version ?? 0)) {
            throw ShelfConflict::changed($shelf->member);
        }

        return $this->shelves[$shelf->member] = $shelf->withVersion($shelf->version + 1);
    }
}
