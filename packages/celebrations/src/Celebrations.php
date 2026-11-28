<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Celebrations\Contract\CelebrationObserver;
use Tilttek\Celebrations\Contract\CelebrationStore;
use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * The members' rewards inboxes: every achievement the application's listeners hand in is kept once, waits to be shown,
 * is grouped with those of the same source and kind, and is shown once.
 *
 * ```php
 * $celebrations->receive(new Achievement($member, 'badges', 'badge', 'badge.first-roll', 'badge.first-roll#1', $at));
 * $celebrations->count($member);                     // 1, for the bell
 * $next = $celebrations->next($member, deviceReducesMotion: false);
 * $celebrations->markSeen($member, $next->group->sequences());
 * ```
 *
 * The library decides no permission and depends on no other package: a listener of the application turns the events
 * of its packages into achievements, and the member who reads an inbox is the member it belongs to.
 */
final readonly class Celebrations
{
    /** @var list<CelebrationObserver> */
    private array $observers;

    /**
     * @param iterable<CelebrationObserver> $observers
     */
    public function __construct(
        private CelebrationStore $store,
        private ClockInterface $clock,
        private Limits $limits = new Limits(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Keeps an achievement in its member's inbox, pending, or lapsed already when its moment to be shown passed.
     * An achievement told again — same member, source and occurrence — is kept once: the entry kept is returned and
     * nothing is told. When the member's pending entries reach their bound, the earliest earned lapse; when the inbox
     * holds more entries than its bound, the first received of the settled ones are dropped.
     */
    public function receive(Achievement $achievement): Entry
    {
        $member = $achievement->member->toString();
        $now = $this->clock->now();
        $known = $this->store->find($member, $achievement->source, $achievement->occurrence);

        if ($known instanceof Entry) {
            return $known->at($now);
        }

        $entry = new Entry($achievement, $this->store->nextSequence($member), EntryState::Pending, $now);

        if (!$entry->isShowableAt($now)) {
            $entry = $entry->at($now);
        } else {
            $this->makeRoom($member, $now);
        }

        $this->store->save($entry);
        $this->tell($member, Change::Received, $entry, $now);
        $this->trim($member);

        return $entry;
    }

    /**
     * Withdraws an entry whose reward was taken back — a badge revoked —: it is never shown, or no longer listed as
     * earned. Withdrawing an entry the inbox does not hold, or holds withdrawn already, does nothing and returns false,
     * so that a revocation told twice is harmless.
     */
    public function withdraw(ResourceReference $member, string $source, string $occurrence): bool
    {
        $entry = $this->store->find($member->toString(), Text::key($source, 'a source'), Text::identifier($occurrence, 'occurrence'));

        if (!$entry instanceof Entry || EntryState::Withdrawn === $entry->state) {
            return false;
        }

        $now = $this->clock->now();
        $this->store->save($entry->settled(EntryState::Withdrawn, $now));
        $this->tell($member->toString(), Change::Withdrawn, $entry, $now);

        return true;
    }

    /**
     * The member's pending entries in groups of one source and one kind, the group holding the earliest earned entry
     * first; each shows its first entries, as many as the limits allow, and counts the rest.
     *
     * @return list<Group>
     */
    public function pending(ResourceReference $member): array
    {
        /** @var array<string, non-empty-list<Entry>> $byGroup */
        $byGroup = [];

        foreach ($this->showable($member->toString(), $this->clock->now()) as $entry) {
            $byGroup[$entry->achievement->source."\n".$entry->achievement->kind][] = $entry;
        }

        $groups = [];

        foreach ($byGroup as $entries) {
            $first = $entries[0]->achievement;
            $groups[] = new Group($first->source, $first->kind, \array_slice($entries, 0, $this->limits->shownPerGroup), \count($entries));
        }

        return $groups;
    }

    /**
     * How many entries wait to be shown to the member.
     */
    public function count(ResourceReference $member): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->store->entries($member->toString(), EntryState::Pending) as $entry) {
            if ($entry->isShowableAt($now)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * What the window shows the member next — the first pending group, with the motion and sound of the member's
     * preferences —, or none when nothing waits or the member turned celebrations off.
     *
     * @param bool $deviceReducesMotion whether the member's device asks for reduced motion
     */
    public function next(ResourceReference $member, bool $deviceReducesMotion = false): ?Celebration
    {
        $preferences = $this->preferences($member);

        if (!$preferences->celebrate) {
            return null;
        }

        $groups = $this->pending($member);

        if ([] === $groups) {
            return null;
        }

        return new Celebration($groups[0], $preferences->motionFor($deviceReducesMotion), $preferences->sound, \count($groups) - 1);
    }

    /**
     * Marks seen the pending entries of these sequences; returns how many were pending.
     *
     * @param list<int> $sequences
     */
    public function markSeen(ResourceReference $member, array $sequences): int
    {
        if (\count($sequences) > $this->limits->pendingPerMember) {
            throw InvalidCelebration::because(\sprintf('At most %d entries are marked seen at once; %d were given.', $this->limits->pendingPerMember, \count($sequences)));
        }

        $wanted = array_flip($sequences);

        return $this->see($member, static fn (Entry $entry): bool => isset($wanted[$entry->sequence]));
    }

    /**
     * Marks seen every pending entry of one source and one kind, those the group does not show too; returns how many.
     */
    public function markGroupSeen(ResourceReference $member, string $source, string $kind): int
    {
        Text::key($source, 'a source');
        Text::key($kind, 'a kind');

        return $this->see($member, static fn (Entry $entry): bool => $entry->achievement->source === $source && $entry->achievement->kind === $kind);
    }

    /**
     * Marks seen every pending entry of the member; returns how many.
     */
    public function markAllSeen(ResourceReference $member): int
    {
        return $this->see($member, static fn (Entry $entry): bool => true);
    }

    /**
     * A page of the member's inbox in every state, the last received first: at most `$limit` entries, 1 to 100, those
     * received before the sequence `$before` when it is given — the sequence of the last entry of the previous page.
     *
     * @return list<Entry>
     */
    public function history(ResourceReference $member, int $limit = 50, ?int $before = null): array
    {
        if ($limit < 1 || $limit > Limits::MAX_PAGE) {
            throw InvalidCelebration::because(\sprintf('A page of the history holds 1 to %d entries; %d is out of bounds.', Limits::MAX_PAGE, $limit));
        }

        $now = $this->clock->now();

        return array_map(static fn (Entry $entry): Entry => $entry->at($now), $this->store->latest($member->toString(), $limit, $before));
    }

    /**
     * The member's preferences, the defaults when the member chose none.
     */
    public function preferences(ResourceReference $member): Preferences
    {
        return $this->store->preferences($member->toString()) ?? new Preferences();
    }

    public function choose(ResourceReference $member, Preferences $preferences): void
    {
        $this->store->savePreferences($member->toString(), $preferences);
        $this->tell($member->toString(), Change::PreferencesChanged, null, $this->clock->now());
    }

    /**
     * Erases the member's inbox and preferences.
     */
    public function forget(ResourceReference $member): void
    {
        $this->store->forget($member->toString());
        $this->tell($member->toString(), Change::Forgotten, null, $this->clock->now());
    }

    /**
     * The member's entries that wait to be shown, in the order the inbox shows them: the earliest earned first, ties
     * in the order they were received.
     *
     * @return list<Entry>
     */
    private function showable(string $member, DateTimeImmutable $now): array
    {
        $entries = array_values(array_filter(
            $this->store->entries($member, EntryState::Pending),
            static fn (Entry $entry): bool => $entry->isShowableAt($now),
        ));
        $seconds = [];
        $micros = [];
        $sequences = [];

        foreach ($entries as $entry) {
            $seconds[] = $entry->achievement->earnedAt->getTimestamp();
            $micros[] = (int) $entry->achievement->earnedAt->format('u');
            $sequences[] = $entry->sequence;
        }

        // Sequences are unique: the entries themselves are never compared.
        array_multisort($seconds, $micros, $sequences, $entries);

        return $entries;
    }

    /**
     * Settles as lapsed the pending entries whose moment passed, then the earliest earned until one more fits.
     */
    private function makeRoom(string $member, DateTimeImmutable $now): void
    {
        foreach ($this->store->entries($member, EntryState::Pending) as $entry) {
            if (!$entry->isShowableAt($now)) {
                $this->store->save($entry->at($now));
                $this->tell($member, Change::Lapsed, $entry, $now);
            }
        }

        $showable = $this->showable($member, $now);
        $excess = \count($showable) - $this->limits->pendingPerMember + 1;

        foreach (\array_slice($showable, 0, max(0, $excess)) as $entry) {
            $this->store->save($entry->settled(EntryState::Lapsed, $now));
            $this->tell($member, Change::Lapsed, $entry, $now);
        }
    }

    /**
     * Drops the first received of the settled entries while the inbox holds more than its bound.
     */
    private function trim(string $member): void
    {
        $excess = $this->store->countEntries($member) - $this->limits->entriesPerMember;

        if ($excess < 1) {
            return;
        }

        foreach ($this->store->entries($member) as $entry) {
            if ($excess < 1) {
                return;
            }

            if (EntryState::Pending !== $entry->state) {
                $this->store->remove($member, $entry->achievement->source, $entry->achievement->occurrence);
                --$excess;
            }
        }
    }

    /**
     * @param Closure(Entry): bool $which
     */
    private function see(ResourceReference $member, Closure $which): int
    {
        $now = $this->clock->now();
        $seen = 0;

        foreach ($this->showable($member->toString(), $now) as $entry) {
            if ($which($entry)) {
                $this->store->save($entry->settled(EntryState::Seen, $now));
                $this->tell($member->toString(), Change::Seen, $entry, $now);
                ++$seen;
            }
        }

        return $seen;
    }

    private function tell(string $member, Change $change, ?Entry $entry, DateTimeImmutable $at): void
    {
        $event = new CelebrationChanged($member, $change, $entry?->achievement->source, $entry?->achievement->occurrence, $at);

        foreach ($this->observers as $observer) {
            $observer->changed($event);
        }
    }
}
