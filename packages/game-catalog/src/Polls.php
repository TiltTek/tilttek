<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Contract\CatalogStore;
use Tilttek\GameCatalog\Exception\EntryNotFound;

/**
 * The votes of the members on the entries: one ballot per member and entry, replaced when they vote again.
 */
final readonly class Polls
{
    public function __construct(private CatalogStore $store)
    {
    }

    /**
     * @throws EntryNotFound when no entry has the identifier of the ballot
     */
    public function vote(Ballot $ballot): void
    {
        $this->store->entry($ballot->entry);
        $this->store->saveBallot($ballot);
    }

    public function withdraw(string $entry, string $member): void
    {
        $this->store->removeBallot($entry, $member);
    }

    public function ballot(string $entry, string $member): ?Ballot
    {
        return $this->store->ballot($entry, $member);
    }

    public function results(string $entry): PollResults
    {
        return PollResults::count($this->store->ballots($entry));
    }
}
