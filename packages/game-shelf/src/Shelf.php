<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Closure;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Exception\ShelfRefused;
use Tilttek\GameShelf\Internal\Identifier;

/**
 * A member's shelf: the copies they hold or held, the games they wish in their own order, and the notes they keep for
 * themselves. The shelf and the wishlist are private until the member shows them; the notes stay private. Each change
 * returns a new shelf.
 */
final readonly class Shelf
{
    public const int MAX_COPIES = 5_000;

    public const int MAX_WISHES = 1_000;

    public const int MAX_NOTES = 5_000;

    /** @var array<string, Copy> by copy, in the order they were placed */
    private array $copies;

    /** @var array<string, Wish> by game, in the member's order */
    private array $wishes;

    /** @var array<string, Note> by game */
    private array $notes;

    /**
     * @param list<Copy> $copies  at most 5000, each identifier once
     * @param list<Wish> $wishes  at most 1000, in the member's order, each game once
     * @param list<Note> $notes   at most 5000, each game once, none empty
     * @param int        $version 0 for a shelf not kept yet
     *
     * @throws InvalidShelf when the member is not an identifier, a list is too long, or holds an identifier twice
     */
    public function __construct(
        public string $member,
        array $copies = [],
        array $wishes = [],
        array $notes = [],
        public Visibility $shelfVisibility = Visibility::Private,
        public Visibility $wishlistVisibility = Visibility::Private,
        public int $version = 0,
    ) {
        Identifier::member($member);
        $this->copies = $this->keyed($copies, static fn (Copy $copy): string => $copy->id, self::MAX_COPIES, 'copies');
        $this->wishes = $this->keyed($wishes, static fn (Wish $wish): string => $wish->game, self::MAX_WISHES, 'wishes');
        $this->notes = $this->keyed($notes, static fn (Note $note): string => $note->game, self::MAX_NOTES, 'notes');

        foreach ($this->notes as $note) {
            if ($note->isEmpty()) {
                throw InvalidShelf::because('A shelf keeps no empty note.');
            }
        }

        if ($version < 0) {
            throw InvalidShelf::because('The version of a shelf is 0 or more.');
        }
    }

    /**
     * @return list<Copy> in the order they were placed
     */
    public function copies(): array
    {
        return array_values($this->copies);
    }

    public function copy(string $id): ?Copy
    {
        return $this->copies[$id] ?? null;
    }

    /**
     * @return list<Copy> the copies of one game, in the order they were placed
     */
    public function copiesOf(string $game): array
    {
        return array_values(array_filter($this->copies, static fn (Copy $copy): bool => $copy->game === $game));
    }

    /**
     * @return list<Copy> the copies the member can bring to a game night
     */
    public function bringable(): array
    {
        return array_values(array_filter($this->copies, static fn (Copy $copy): bool => $copy->canBeBrought()));
    }

    /**
     * @return list<Wish> in the member's own order
     */
    public function wishes(): array
    {
        return array_values($this->wishes);
    }

    public function wish(string $game): ?Wish
    {
        return $this->wishes[$game] ?? null;
    }

    /**
     * The wishlist: the wishes from "must have" to "don't buy this", then those without a level; the member's own
     * order within each level.
     *
     * @return list<Wish>
     */
    public function wishlist(): array
    {
        $levels = [];

        foreach ($this->wishes as $wish) {
            $levels[$wish->level->value ?? \PHP_INT_MAX][] = $wish;
        }

        ksort($levels);

        return array_merge(...$levels);
    }

    /**
     * @return list<Note>
     */
    public function notes(): array
    {
        return array_values($this->notes);
    }

    public function note(string $game): ?Note
    {
        return $this->notes[$game] ?? null;
    }

    /**
     * Places a copy, or replaces the copy of the same identifier.
     *
     * @throws ShelfRefused when the copy is new and the shelf holds 5000 copies
     */
    public function withCopy(Copy $copy): self
    {
        if (!isset($this->copies[$copy->id]) && \count($this->copies) >= self::MAX_COPIES) {
            throw ShelfRefused::because(\sprintf('A shelf holds at most %d copies.', self::MAX_COPIES));
        }

        $copies = $this->copies;
        $copies[$copy->id] = $copy;

        return $this->with(copies: $copies);
    }

    /**
     * @throws ShelfRefused when the copy is not on the shelf
     */
    public function withoutCopy(string $id): self
    {
        if (!isset($this->copies[$id])) {
            throw ShelfRefused::because(\sprintf('The copy %s is not on the shelf.', $id));
        }

        $copies = $this->copies;
        unset($copies[$id]);

        return $this->with(copies: $copies);
    }

    /**
     * Wishes a game, last of the member's order, or replaces the wish of the same game at its place.
     *
     * @throws ShelfRefused when the wish is new and the shelf holds 1000 wishes
     */
    public function withWish(Wish $wish): self
    {
        if (!isset($this->wishes[$wish->game]) && \count($this->wishes) >= self::MAX_WISHES) {
            throw ShelfRefused::because(\sprintf('A shelf holds at most %d wishes.', self::MAX_WISHES));
        }

        $wishes = $this->wishes;
        $wishes[$wish->game] = $wish;

        return $this->with(wishes: $wishes);
    }

    /**
     * @throws ShelfRefused when the game is not wished
     */
    public function withoutWish(string $game): self
    {
        $wishes = $this->wishes;
        unset($wishes[$this->wished($game)->game]);

        return $this->with(wishes: $wishes);
    }

    /**
     * Moves a wish just before another in the member's order.
     *
     * @throws ShelfRefused when either game is not wished, or both are the same
     */
    public function withWishBefore(string $game, string $before): self
    {
        return $this->moved($game, $before, 0);
    }

    /**
     * Moves a wish just after another in the member's order.
     *
     * @throws ShelfRefused when either game is not wished, or both are the same
     */
    public function withWishAfter(string $game, string $after): self
    {
        return $this->moved($game, $after, 1);
    }

    /**
     * Keeps the note of a game, in place of the earlier one; an empty note drops it.
     *
     * @throws ShelfRefused when the note is new and the shelf holds 5000 notes
     */
    public function withNote(Note $note): self
    {
        $notes = $this->notes;

        if ($note->isEmpty()) {
            unset($notes[$note->game]);

            return $this->with(notes: $notes);
        }

        if (!isset($notes[$note->game]) && \count($notes) >= self::MAX_NOTES) {
            throw ShelfRefused::because(\sprintf('A shelf holds at most %d notes.', self::MAX_NOTES));
        }

        $notes[$note->game] = $note;

        return $this->with(notes: $notes);
    }

    public function withVisibility(Visibility $shelf, Visibility $wishlist): self
    {
        return new self($this->member, $this->copies(), $this->wishes(), $this->notes(), $shelf, $wishlist, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->member, $this->copies(), $this->wishes(), $this->notes(), $this->shelfVisibility, $this->wishlistVisibility, $version);
    }

    /**
     * The shelf as a viewer sees it: the copies when the shelf is shown to them, without their location for another
     * viewer than the member; the wishlist when it is shown to them; the notes for the member alone.
     */
    public function view(Viewer $viewer): ShelfView
    {
        $owner = Viewer::Owner === $viewer;

        return new ShelfView(
            $this->member,
            $viewer,
            $this->shelfVisibility->shows($viewer)
                ? array_map(static fn (Copy $copy): Copy => $owner ? $copy : $copy->withoutPrivateDetails(), $this->copies())
                : [],
            $this->wishlistVisibility->shows($viewer) ? $this->wishlist() : [],
            $owner ? $this->notes() : [],
        );
    }

    /**
     * @param ?array<string, Copy> $copies
     * @param ?array<string, Wish> $wishes
     * @param ?array<string, Note> $notes
     */
    private function with(?array $copies = null, ?array $wishes = null, ?array $notes = null): self
    {
        return new self(
            $this->member,
            array_values($copies ?? $this->copies),
            array_values($wishes ?? $this->wishes),
            array_values($notes ?? $this->notes),
            $this->shelfVisibility,
            $this->wishlistVisibility,
            $this->version,
        );
    }

    private function wished(string $game): Wish
    {
        return $this->wishes[$game] ?? throw ShelfRefused::because(\sprintf('The game %s is not wished.', $game));
    }

    /**
     * @param int<0, 1> $offset 0 to move before the other wish, 1 after it
     */
    private function moved(string $game, string $other, int $offset): self
    {
        $moved = $this->wished($game);
        $this->wished($other);

        if ($game === $other) {
            throw ShelfRefused::because('A wish moves before or after another wish.');
        }

        $wishes = $this->wishes;
        unset($wishes[$game]);
        $position = (int) array_search($other, array_keys($wishes), true) + $offset;

        return $this->with(wishes: \array_slice($wishes, 0, $position, true) + [$game => $moved] + $wishes);
    }

    /**
     * @template T of object
     *
     * @param list<T>            $values
     * @param Closure(T): string $key
     *
     * @return array<string, T>
     */
    private function keyed(array $values, Closure $key, int $max, string $what): array
    {
        if (\count($values) > $max) {
            throw InvalidShelf::because(\sprintf('A shelf holds at most %d %s.', $max, $what));
        }

        $keyed = [];

        foreach ($values as $value) {
            $id = $key($value);

            if (isset($keyed[$id])) {
                throw InvalidShelf::because(\sprintf('The %s of a shelf hold %s twice.', $what, $id));
            }

            $keyed[$id] = $value;
        }

        return $keyed;
    }
}
