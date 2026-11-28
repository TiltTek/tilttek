<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Store;

use Tilttek\Celebrations\Contract\CelebrationStore;
use Tilttek\Celebrations\Entry;
use Tilttek\Celebrations\EntryState;
use Tilttek\Celebrations\Preferences;

/**
 * The inboxes in memory, for tests and for applications that rebuild them at each start.
 */
final class InMemoryCelebrationStore implements CelebrationStore
{
    /** @var array<string, array<string, Entry>> the entries by member, then by source and occurrence */
    private array $entries = [];

    /** @var array<string, positive-int> the last sequence given, by member */
    private array $sequences = [];

    /** @var array<string, Preferences> */
    private array $preferences = [];

    public function find(string $member, string $source, string $occurrence): ?Entry
    {
        return $this->entries[$member][$this->key($source, $occurrence)] ?? null;
    }

    public function entries(string $member, ?EntryState $state = null): array
    {
        $entries = array_filter(
            $this->entries[$member] ?? [],
            static fn (Entry $entry): bool => !$state instanceof EntryState || $entry->state === $state,
        );
        usort($entries, static fn (Entry $a, Entry $b): int => $a->sequence <=> $b->sequence);

        return $entries;
    }

    public function countEntries(string $member, ?EntryState $state = null): int
    {
        return \count($this->entries($member, $state));
    }

    public function latest(string $member, int $limit, ?int $before = null): array
    {
        $entries = array_filter(
            $this->entries($member),
            static fn (Entry $entry): bool => null === $before || $entry->sequence < $before,
        );

        return \array_slice(array_reverse($entries), 0, $limit);
    }

    public function nextSequence(string $member): int
    {
        $next = ($this->sequences[$member] ?? 0) + 1;
        $this->sequences[$member] = $next;

        return $next;
    }

    public function save(Entry $entry): void
    {
        $this->entries[$entry->member()][$this->key($entry->achievement->source, $entry->achievement->occurrence)] = $entry;
    }

    public function remove(string $member, string $source, string $occurrence): void
    {
        unset($this->entries[$member][$this->key($source, $occurrence)]);
    }

    public function preferences(string $member): ?Preferences
    {
        return $this->preferences[$member] ?? null;
    }

    public function savePreferences(string $member, Preferences $preferences): void
    {
        $this->preferences[$member] = $preferences;
    }

    public function forget(string $member): void
    {
        unset($this->entries[$member], $this->preferences[$member]);
    }

    private function key(string $source, string $occurrence): string
    {
        return $source."\n".$occurrence;
    }
}
