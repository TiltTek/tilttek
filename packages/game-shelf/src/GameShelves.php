<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Closure;
use Throwable;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Import\CollectionImport;
use Tilttek\GameCatalog\Import\CollectionItem;
use Tilttek\GameShelf\Contract\ShelfObserver;
use Tilttek\GameShelf\Contract\ShelfStore;
use Tilttek\GameShelf\Event\CopyAdded;
use Tilttek\GameShelf\Event\CopyOfferedForTrade;
use Tilttek\GameShelf\Event\CopyRemoved;
use Tilttek\GameShelf\Event\CopyWithdrawnFromTrade;
use Tilttek\GameShelf\Event\GameWished;
use Tilttek\GameShelf\Event\ShelfEvent;
use Tilttek\GameShelf\Event\WishWithdrawn;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Exception\ShelfRefused;
use Tilttek\GameShelf\Internal\Identifier;

/**
 * The service of the shelves: copies, wishes and their order, notes, visibility, the import of a collection file, and
 * the games members can bring to a game night. It reads the shelf from the store, changes it, keeps it, then tells
 * the observer what happened, each fact once. It keeps no state between calls: everything is in the store.
 *
 * Every method that changes a shelf raises `InvalidShelf` when the member is not an identifier, and `ShelfConflict`
 * when the shelf changed since it was read. When the observer fails on a fact, it is still told the others, then the
 * first exception it raised is raised again: the change is kept.
 */
final readonly class GameShelves
{
    /** The most members whose games `bringable()` reads at once. */
    public const int MAX_MEMBERS = 2_000;

    /** The most games `bringable()` gives at once. */
    public const int MAX_BRINGABLE = 10_000;

    public function __construct(
        private ShelfStore $store,
        private Catalog $catalog,
        private ?ShelfObserver $observer = null,
    ) {
    }

    /**
     * @throws InvalidShelf when the member is not an identifier
     */
    public function shelf(string $member): Shelf
    {
        return $this->store->shelf(Identifier::member($member));
    }

    /**
     * The shelf as a viewer sees it.
     *
     * @throws InvalidShelf when the member is not an identifier
     */
    public function view(string $member, Viewer $viewer): ShelfView
    {
        return $this->shelf($member)->view($viewer);
    }

    /**
     * Places a copy, or replaces the copy of the same identifier; a copy that replaces an imported copy of the same game
     * stays imported.
     *
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws ShelfRefused  when the game is hidden in the catalogue, or the shelf is full
     */
    public function place(string $member, Copy $copy): Shelf
    {
        return $this->change($member, function (Shelf $shelf) use ($copy): Shelf {
            $earlier = $shelf->copy($copy->id);

            if ($earlier?->game !== $copy->game) {
                $this->refuseHidden($copy->game);
            } elseif ($earlier->imported) {
                $copy = $copy->withImported(true);
            }

            return $shelf->withCopy($copy);
        });
    }

    /**
     * @throws ShelfRefused when the copy is not on the shelf
     */
    public function remove(string $member, string $copy): Shelf
    {
        return $this->change($member, static fn (Shelf $shelf): Shelf => $shelf->withoutCopy($copy));
    }

    /**
     * Wishes a game, last of the member's order, or replaces the wish of the same game at its place.
     *
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws ShelfRefused  when the game is hidden in the catalogue, or the shelf holds 1000 wishes
     */
    public function wish(string $member, Wish $wish): Shelf
    {
        return $this->change($member, function (Shelf $shelf) use ($wish): Shelf {
            if (!$shelf->wish($wish->game) instanceof Wish) {
                $this->refuseHidden($wish->game);
            }

            return $shelf->withWish($wish);
        });
    }

    /**
     * @throws ShelfRefused when the game is not wished
     */
    public function unwish(string $member, string $game): Shelf
    {
        return $this->change($member, static fn (Shelf $shelf): Shelf => $shelf->withoutWish($game));
    }

    /**
     * @throws ShelfRefused when either game is not wished, or both are the same
     */
    public function moveWishBefore(string $member, string $game, string $before): Shelf
    {
        return $this->change($member, static fn (Shelf $shelf): Shelf => $shelf->withWishBefore($game, $before));
    }

    /**
     * @throws ShelfRefused when either game is not wished, or both are the same
     */
    public function moveWishAfter(string $member, string $game, string $after): Shelf
    {
        return $this->change($member, static fn (Shelf $shelf): Shelf => $shelf->withWishAfter($game, $after));
    }

    /**
     * Keeps the note of a game of the catalogue, in place of the earlier one; an empty note drops it.
     *
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws ShelfRefused  when the shelf holds 5000 notes
     */
    public function note(string $member, Note $note): Shelf
    {
        return $this->change($member, function (Shelf $shelf) use ($note): Shelf {
            if (!$note->isEmpty()) {
                $this->catalog->get($note->game);
            }

            return $shelf->withNote($note);
        });
    }

    /**
     * Shows the shelf and the wishlist to whom the member chooses.
     */
    public function share(string $member, Visibility $shelf, Visibility $wishlist): Shelf
    {
        return $this->change($member, static fn (Shelf $kept): Shelf => $kept->withVisibility($shelf, $wishlist));
    }

    /**
     * Imports what `tilttek/game-catalog` found in a collection file. A game owned, held before, preordered or for
     * trade is one copy whose identifier is the game's: a later import replaces its statuses and keeps the rest of
     * it; the copies placed by hand under other identifiers are never touched. A game wished is a wish whose kinds the
     * file replaces, with the level its priority gives when it has one; a new wish goes last of the member's order.
     * The plays the file gives replace those of the note.
     *
     * By default a copy the file no longer names stays. `ImportMode::Synchronize` moves the imported copies the file no
     * longer names as a copy to previously owned, keeping the rest of them; the copies entered by hand, and the copies
     * of a game the file names but the import left out — hidden in the catalogue, of a kind not imported, or with
     * statuses that do not go together —, are never touched.
     */
    public function import(string $member, CollectionImport $import, ImportMode $mode = ImportMode::Add): ShelfImport
    {
        $kept = ImportMode::Synchronize === $mode ? $this->leftOut($import) : null;
        $result = new ShelfImport($this->shelf($member));
        $after = $this->change($member, function (Shelf $shelf) use ($import, $kept, &$result): Shelf {
            $result = $this->imported($shelf, $import->items, $kept);

            return $result->shelf;
        });

        return new ShelfImport(
            $after,
            $result->copiesAdded,
            $result->copiesUpdated,
            $result->wishesAdded,
            $result->wishesUpdated,
            $result->playsUpdated,
            $result->unchanged,
            $result->refused,
            $result->copiesPreviouslyOwned,
        );
    }

    /**
     * The games the members own and can bring to a game night, once per member and game, member after member in the
     * order given; read it through the adapter of a game night's port.
     *
     * @param list<string> $members at most 2000
     * @param int          $limit   1 to 10 000, the most games given
     *
     * @return iterable<BringableGame>
     *
     * @throws InvalidShelf when a member is not an identifier, there are too many, or the limit is out of its bounds
     */
    public function bringable(array $members, int $limit): iterable
    {
        if (\count($members) > self::MAX_MEMBERS) {
            throw InvalidShelf::because(\sprintf('The games of at most %d members are read at once.', self::MAX_MEMBERS));
        }

        if ($limit < 1 || $limit > self::MAX_BRINGABLE) {
            throw InvalidShelf::because(\sprintf('At most 1 to %d games are given at once.', self::MAX_BRINGABLE));
        }

        array_map(Identifier::member(...), $members);

        return $this->games(array_values(array_unique($members)), $limit);
    }

    /**
     * @param list<string> $members
     *
     * @return iterable<BringableGame>
     */
    private function games(array $members, int $limit): iterable
    {
        foreach ($members as $member) {
            $given = [];

            foreach ($this->store->shelf($member)->bringable() as $copy) {
                if (isset($given[$copy->game])) {
                    continue;
                }

                $given[$copy->game] = true;

                yield new BringableGame($copy->game, $member);

                if (--$limit < 1) {
                    return;
                }
            }
        }
    }

    /**
     * The games of the catalogue the file names but the import left out: hidden, or of a kind not imported.
     *
     * @return array<string, true>
     */
    private function leftOut(CollectionImport $import): array
    {
        $games = [];

        foreach ([...$import->unmatched, ...$import->otherKinds] as $identifier) {
            $entry = $this->catalog->findByIdentifier($identifier);

            if ($entry instanceof Entry) {
                $games[$entry->id] = true;
            }
        }

        return $games;
    }

    /**
     * @param list<CollectionItem> $items
     * @param ?array<string, true> $kept  the games whose copies a synchronized import keeps, `null` when it adds only
     */
    private function imported(Shelf $shelf, array $items, ?array $kept): ShelfImport
    {
        $copies = [];
        $wishes = [];
        $notes = [];

        foreach ($shelf->copies() as $copy) {
            $copies[$copy->id] = $copy;
        }

        foreach ($shelf->wishes() as $wish) {
            $wishes[$wish->game] = $wish;
        }

        foreach ($shelf->notes() as $note) {
            $notes[$note->game] = $note;
        }

        $counts = ['copiesAdded' => 0, 'copiesUpdated' => 0, 'wishesAdded' => 0, 'wishesUpdated' => 0, 'playsUpdated' => 0, 'unchanged' => 0];
        $refused = [];

        foreach ($items as $item) {
            if (null !== $kept && [] !== array_filter(array_map(CopyStatus::fromCollection(...), $item->statuses))) {
                $kept[$item->entry] = true;
            }

            try {
                $changes = $this->importItem($item, $copies, $wishes, $notes);
            } catch (InvalidShelf) {
                $refused[] = $item->entry;

                continue;
            }

            if ([] === $changes) {
                ++$counts['unchanged'];
            }

            foreach ($changes as $change) {
                ++$counts[$change];
            }
        }

        $previouslyOwned = null === $kept ? 0 : $this->synchronized($copies, $kept);

        return new ShelfImport(
            new Shelf($shelf->member, array_values($copies), array_values($wishes), array_values($notes), $shelf->shelfVisibility, $shelf->wishlistVisibility, $shelf->version),
            $counts['copiesAdded'],
            $counts['copiesUpdated'],
            $counts['wishesAdded'],
            $counts['wishesUpdated'],
            $counts['playsUpdated'],
            $counts['unchanged'],
            $refused,
            $previouslyOwned,
        );
    }

    /**
     * Moves the imported copies whose game the file no longer names to previously owned.
     *
     * @param array<string, Copy> $copies
     * @param array<string, true> $kept   the games the file names
     *
     * @return int the copies moved
     */
    private function synchronized(array &$copies, array $kept): int
    {
        $moved = 0;

        foreach ($copies as $id => $copy) {
            if (!$copy->imported || isset($kept[$copy->game]) || [CopyStatus::PreviouslyOwned] === $copy->statuses) {
                continue;
            }

            $copies[$id] = $copy->withStatuses([CopyStatus::PreviouslyOwned]);
            ++$moved;
        }

        return $moved;
    }

    /**
     * @param array<string, Copy> $copies
     * @param array<string, Wish> $wishes
     * @param array<string, Note> $notes
     *
     * @return list<'copiesAdded'|'copiesUpdated'|'wishesAdded'|'wishesUpdated'|'playsUpdated'>
     *
     * @throws InvalidShelf when the statuses do not go together, or the shelf is full
     */
    private function importItem(CollectionItem $item, array &$copies, array &$wishes, array &$notes): array
    {
        $game = $item->entry;
        $statuses = array_values(array_filter(array_map(CopyStatus::fromCollection(...), $item->statuses)));
        $kinds = array_values(array_filter(array_map(WishKind::fromCollection(...), $item->statuses)));
        $copy = $copies[$game] ?? null;
        $wish = $wishes[$game] ?? null;
        $note = $notes[$game] ?? null;
        $changes = [];

        if ([] !== $statuses && $statuses !== $copy?->statuses) {
            $changes['copiesAdded'] = $copy?->withStatuses($statuses) ?? new Copy($game, $game, $statuses, imported: true);
        }

        $level = (null === $item->priority ? null : WishLevel::tryFrom($item->priority)) ?? $wish?->level;

        if ([] !== $kinds && ($kinds !== $wish?->kinds || $level !== $wish->level)) {
            $changes['wishesAdded'] = new Wish($game, $kinds, $level);
        }

        if (null !== $item->plays && $item->plays !== ($note->plays ?? 0)) {
            $changes['playsUpdated'] = ($note ?? new Note($game))->withPlays($item->plays);
        }

        $this->refuseWhenFull($changes, $copy, $wish, $note, $copies, $wishes, $notes);
        $counted = [];

        foreach ($changes as $change) {
            if ($change instanceof Copy) {
                $copies[$game] = $change;
                $counted[] = $copy instanceof Copy ? 'copiesUpdated' : 'copiesAdded';
            } elseif ($change instanceof Wish) {
                $wishes[$game] = $change;
                $counted[] = $wish instanceof Wish ? 'wishesUpdated' : 'wishesAdded';
            } else {
                $counted[] = 'playsUpdated';
                $notes[$game] = $change;

                if ($change->isEmpty()) {
                    unset($notes[$game]);
                }
            }
        }

        return $counted;
    }

    /**
     * @param array<string, Copy|Wish|Note> $changes
     * @param array<string, Copy>           $copies
     * @param array<string, Wish>           $wishes
     * @param array<string, Note>           $notes
     *
     * @throws InvalidShelf when a change adds to a part of the shelf that is full
     */
    private function refuseWhenFull(array $changes, ?Copy $copy, ?Wish $wish, ?Note $note, array $copies, array $wishes, array $notes): void
    {
        if (isset($changes['copiesAdded']) && !$copy instanceof Copy && \count($copies) >= Shelf::MAX_COPIES
            || isset($changes['wishesAdded']) && !$wish instanceof Wish && \count($wishes) >= Shelf::MAX_WISHES
            || isset($changes['playsUpdated']) && !$note instanceof Note && \count($notes) >= Shelf::MAX_NOTES) {
            throw InvalidShelf::because('The shelf is full.');
        }
    }

    /**
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws ShelfRefused  when it is hidden
     */
    private function refuseHidden(string $game): void
    {
        if ($this->catalog->get($game)->hidden) {
            throw ShelfRefused::because(\sprintf('The game %s is hidden in the catalogue.', $game));
        }
    }

    /**
     * Keeps the change, then tells the observer each fact. A fact the observer fails on does not stop the others: every
     * fact is told, then the first exception is raised again, the shelf being kept.
     *
     * @param Closure(Shelf): Shelf $change
     */
    private function change(string $member, Closure $change): Shelf
    {
        $before = $this->shelf($member);
        $after = $this->store->save($change($before));
        $failure = null;

        foreach ($this->events($before, $after) as $event) {
            try {
                $this->observer?->notify($event);
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $after;
    }

    /**
     * @return list<ShelfEvent>
     */
    private function events(Shelf $before, Shelf $after): array
    {
        $member = $after->member;
        $events = [];

        foreach ($before->copies() as $copy) {
            $kept = $after->copy($copy->id);

            if ($kept?->game !== $copy->game) {
                if ($copy->has(CopyStatus::ForTrade)) {
                    $events[] = new CopyWithdrawnFromTrade($member, $copy->id, $copy->game);
                }

                $events[] = new CopyRemoved($member, $copy->id, $copy->game);
            } elseif ($copy->has(CopyStatus::ForTrade) && !$kept->has(CopyStatus::ForTrade)) {
                $events[] = new CopyWithdrawnFromTrade($member, $copy->id, $copy->game);
            }
        }

        foreach ($after->copies() as $copy) {
            $earlier = $before->copy($copy->id);
            $added = $earlier?->game !== $copy->game;

            if ($added) {
                $events[] = new CopyAdded($member, $copy->id, $copy->game);
            }

            if ($copy->has(CopyStatus::ForTrade) && ($added || !$earlier->has(CopyStatus::ForTrade))) {
                $events[] = new CopyOfferedForTrade($member, $copy->id, $copy->game);
            }
        }

        foreach ($after->wishes() as $wish) {
            if (!$before->wish($wish->game) instanceof Wish) {
                $events[] = new GameWished($member, $wish->game);
            }
        }

        foreach ($before->wishes() as $wish) {
            if (!$after->wish($wish->game) instanceof Wish) {
                $events[] = new WishWithdrawn($member, $wish->game);
            }
        }

        return $events;
    }
}
