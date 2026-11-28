<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Store;

use Override;
use Tilttek\Downtime\Contract\EntryStore;
use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Exception\DowntimeConflict;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Stage;

/**
 * The entries in memory, for tests: they last as long as the object.
 */
final class InMemoryEntryStore implements EntryStore
{
    /** @var array<string, Entry> by identifier, in the order they were chosen */
    private array $entries = [];

    #[Override]
    public function entry(string $id): ?Entry
    {
        return $this->entries[$id] ?? null;
    }

    #[Override]
    public function save(array $entries): array
    {
        $versions = [];

        foreach ($entries as $entry) {
            $held = \array_key_exists($entry->id, $versions) ? $versions[$entry->id] : ($this->entries[$entry->id]->version ?? 0);

            if ($entry->version !== $held) {
                throw DowntimeConflict::changed($entry->id);
            }

            $versions[$entry->id] = $entry->version + 1;
        }

        $kept = [];

        foreach ($entries as $entry) {
            $kept[] = $this->entries[$entry->id] = $entry->withVersion($entry->version + 1);
        }

        return $kept;
    }

    #[Override]
    public function entriesOf(string $campaign, string $character, int $limit): array
    {
        return $this->read($limit, static fn (Entry $entry): bool => $entry->campaign === $campaign && $entry->character === $character);
    }

    #[Override]
    public function openOf(string $campaign, string $character, int $limit): array
    {
        return $this->read($limit, static fn (Entry $entry): bool => $entry->campaign === $campaign && $entry->character === $character && $entry->stage->isOpen());
    }

    #[Override]
    public function queuedIn(string $campaign, int $limit): array
    {
        return $this->read($limit, static fn (Entry $entry): bool => $entry->campaign === $campaign && Stage::Queued === $entry->stage);
    }

    /**
     * @param callable(Entry): bool $filter
     *
     * @return list<Entry>
     */
    private function read(int $limit, callable $filter): array
    {
        if ($limit < 1) {
            throw InvalidDowntime::because('A store reads at least one entry at once.');
        }

        return \array_slice(array_values(array_filter($this->entries, $filter)), 0, $limit);
    }
}
