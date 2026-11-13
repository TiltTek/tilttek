<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Store;

use Override;
use Tilttek\SecretRolls\Contract\SecretRollStore;
use Tilttek\SecretRolls\Exception\JournalConflict;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Journal\JournalEntry;

/**
 * The journals and the sealed rolls in memory, for tests and for a table that lives in one process.
 */
final class InMemorySecretRollStore implements SecretRollStore
{
    /** @var array<string, list<JournalEntry>> */
    private array $entries = [];

    /** @var array<string, HiddenRoll> */
    private array $hidden = [];

    #[Override]
    public function entries(string $scene): array
    {
        return $this->entries[$scene] ?? [];
    }

    #[Override]
    public function commit(JournalEntry $entry, HiddenRoll $roll): void
    {
        $this->append($entry);
        $this->hidden[$roll->roll()] = $roll;
    }

    #[Override]
    public function append(JournalEntry $entry): void
    {
        $expected = \count($this->entries($entry->scene)) + 1;

        if ($entry->position !== $expected) {
            throw JournalConflict::at($entry->scene, $entry->position, $expected);
        }

        $this->entries[$entry->scene][] = $entry;
    }

    #[Override]
    public function hidden(string $roll): ?HiddenRoll
    {
        return $this->hidden[$roll] ?? null;
    }
}
