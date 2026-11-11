<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Store\CompletionStore;
use Tilttek\Collections\Store\CopyStore;

/**
 * The members' collections: copies given under a key — one item, a set whole, the base sets on arrival —, the album
 * of a member with their doubles and the progress of each set, the owner of a copy and whether it may change hands,
 * the moves of a trade, and a copy taken back. A set completed for the first time is told with its rewards.
 */
final readonly class Collections
{
    public function __construct(
        public CatalogProvider $catalog,
        public CopyStore $copies,
        public CompletionStore $completions,
        private ClockInterface $clock,
        private ?CollectionObserver $observer = null,
    ) {
    }

    /**
     * Gives a member a copy of an item under a key; the same key given again returns the copy it gave. A copy given
     * with `$tradable` false stays bound to its member — an item earned by a quest, for instance —; an item the
     * catalog marks as not tradable is bound whatever is asked.
     *
     * @throws NotFound          when the catalog has no such item
     * @throws CollectionRefused when the key gave a copy of another item, or to another member
     */
    public function grant(ResourceReference $member, string $item, Source $source, string|CollectionKey $key, ?DateTimeImmutable $at = null, bool $tradable = true): Copy
    {
        $definition = $this->catalog->catalog()->item($item);
        $key = \is_string($key) ? CollectionKey::fromString($key) : $key;
        $copy = new Copy(Copy::idFor($key), $item, $member, $source, $key->value, $at ?? $this->clock->now(), $definition->tradable && $tradable);

        if (!$this->copies->add($copy)) {
            $given = $this->copies->find($copy->id);

            if ($given?->item !== $item || (!$given->moved && !$given->owner->equals($member))) {
                throw CollectionRefused::because(\sprintf('The key "%s" already gave another copy.', $key->value));
            }

            return $given;
        }

        $this->observer?->granted($copy);
        $this->complete($member, [$item], $copy->acquiredAt);

        return $copy;
    }

    /**
     * Gives a member every item of a set — an earned set once the exchange is confirmed, a paid set once the purchase
     * or the gift is —, each under the key followed by `#` and the item's code.
     *
     * @return list<Copy>
     */
    public function grantSet(ResourceReference $member, string $set, Source $source, string|CollectionKey $key, ?DateTimeImmutable $at = null): array
    {
        $key = \is_string($key) ? CollectionKey::fromString($key) : $key;
        $at ??= $this->clock->now();

        return array_map(
            fn (Item $item): Copy => $this->grant($member, $item->code, $source, $key->derive($item->code), $at),
            $this->catalog->catalog()->itemsOf($set),
        );
    }

    /**
     * Gives a member arriving on the site every base set, each under the key followed by `#` and the set's code.
     *
     * @return list<Copy>
     */
    public function welcome(ResourceReference $member, string $key, ?DateTimeImmutable $at = null): array
    {
        $key = CollectionKey::fromString($key);
        $copies = [];

        foreach ($this->catalog->catalog()->sets() as $set) {
            if (SetKind::Base === $set->kind) {
                $copies = [...$copies, ...$this->grantSet($member, $set->code, Source::Welcome, $key->derive($set->code), $at)];
            }
        }

        return $copies;
    }

    /**
     * A copy by its identifier.
     *
     * @throws NotFound when no copy has it
     */
    public function copy(string $id): Copy
    {
        return $this->copies->find($id) ?? throw NotFound::of('copy', $id);
    }

    /**
     * @return list<Copy>
     */
    public function copiesOf(ResourceReference $member): array
    {
        return $this->copies->ofMember($member);
    }

    public function ownerOf(string $copy): ?ResourceReference
    {
        return $this->copies->find($copy)?->owner;
    }

    public function isTradable(string $copy): bool
    {
        return true === $this->copies->find($copy)?->tradable;
    }

    /**
     * Moves the copies, each from the member who gives it to another, all of them or none: every copy is checked
     * before any moves. Run it in a transaction of the store when two processes may move the same copies.
     *
     * @param list<CopyMove> $moves
     *
     * @throws CollectionRefused when a copy does not belong to the member who gives it
     */
    public function move(array $moves): void
    {
        foreach ($moves as $move) {
            if (true !== $this->ownerOf($move->copy)?->equals($move->from)) {
                throw CollectionRefused::because(\sprintf('The copy "%s" does not belong to "%s".', $move->copy, $move->from->toString()));
            }
        }

        $at = $this->clock->now();

        foreach ($moves as $move) {
            if (!$this->copies->reassign($move->copy, $move->from, $move->to)) {
                throw CollectionRefused::because(\sprintf('The copy "%s" changed hands meanwhile.', $move->copy));
            }

            $this->complete($move->to, [$this->copy($move->copy)->item], $at);
        }
    }

    /**
     * Takes back the copy a key gave — a purchase refunded, an activity cancelled — if it is still with its member
     * and never changed hands; returns whether it did.
     */
    public function takeBack(ResourceReference $member, string|CollectionKey $key): bool
    {
        return $this->copies->takeBack(Copy::idFor(\is_string($key) ? CollectionKey::fromString($key) : $key), $member);
    }

    /**
     * A member's album: every item with the copies they own, and every set with its progress.
     */
    public function album(ResourceReference $member): Album
    {
        $catalog = $this->catalog->catalog();
        $counts = array_count_values(array_map(static fn (Copy $copy): string => $copy->item, $this->copies->ofMember($member)));
        $completed = array_map(static fn (SetCompletion $completion): string => $completion->set, $this->completions->ofMember($member));
        $sets = [];

        foreach ($catalog->sets() as $set) {
            $items = $catalog->itemsOf($set->code);
            $owned = array_values(array_filter($items, static fn (Item $item): bool => isset($counts[$item->code])));
            $missing = array_values(array_filter($items, static fn (Item $item): bool => !isset($counts[$item->code])));
            $sets[] = new SetProgress($set, $owned, $missing, \in_array($set->code, $completed, true));
        }

        return new Album(
            array_map(static fn (Item $item): Holding => new Holding($item, $counts[$item->code] ?? 0), $catalog->items()),
            $sets,
        );
    }

    /**
     * Writes and tells the sets the member completes for the first time with these items.
     *
     * @param list<string> $items
     */
    private function complete(ResourceReference $member, array $items, DateTimeImmutable $at): void
    {
        $catalog = $this->catalog->catalog();
        $owned = array_map(static fn (Copy $copy): string => $copy->item, $this->copies->ofMember($member));

        foreach ($catalog->sets() as $set) {
            $needed = array_map(static fn (Item $item): string => $item->code, $catalog->itemsOf($set->code));

            if ([] === array_intersect($items, $needed) || [] !== array_diff($needed, $owned)) {
                continue;
            }

            $completion = new SetCompletion($set->code, $member, $at);

            if ($this->completions->add($completion)) {
                $this->observer?->setCompleted(new SetCompleted($set, $completion));
            }
        }
    }
}
