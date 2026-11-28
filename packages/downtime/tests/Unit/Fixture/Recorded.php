<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Fixture;

use Override;
use Tilttek\Downtime\Contract\EntryStore;
use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Store\InMemoryEntryStore;

/**
 * The entries in memory, with the reads asked of the store, in order: their name and their limit.
 */
final class Recorded implements EntryStore
{
    /** @var list<array{string, int}> */
    public array $reads = [];

    private readonly InMemoryEntryStore $store;

    public function __construct()
    {
        $this->store = new InMemoryEntryStore();
    }

    #[Override]
    public function entry(string $id): ?Entry
    {
        return $this->store->entry($id);
    }

    #[Override]
    public function save(array $entries): array
    {
        return $this->store->save($entries);
    }

    #[Override]
    public function entriesOf(string $campaign, string $character, int $limit): array
    {
        $this->reads[] = ['entriesOf', $limit];

        return $this->store->entriesOf($campaign, $character, $limit);
    }

    #[Override]
    public function openOf(string $campaign, string $character, int $limit): array
    {
        $this->reads[] = ['openOf', $limit];

        return $this->store->openOf($campaign, $character, $limit);
    }

    #[Override]
    public function queuedIn(string $campaign, int $limit): array
    {
        $this->reads[] = ['queuedIn', $limit];

        return $this->store->queuedIn($campaign, $limit);
    }
}
