<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * The pending entries of one source and one kind, shown together — "3 new badges" —: the first entries, in the order
 * the inbox shows them, and how many the group holds in all.
 */
final readonly class Group
{
    /** @var non-empty-list<Entry> */
    public array $entries;

    /**
     * @param list<Entry> $entries
     *
     * @throws InvalidCelebration
     */
    public function __construct(
        public string $source,
        public string $kind,
        array $entries,
        public int $count,
    ) {
        if ([] === $entries) {
            throw InvalidCelebration::because('A group shows at least one entry.');
        }

        foreach ($entries as $entry) {
            if ($entry->achievement->source !== $source || $entry->achievement->kind !== $kind) {
                throw InvalidCelebration::because(\sprintf('A group holds the entries of one source and one kind; sequence %d is not of "%s" and "%s".', $entry->sequence, $source, $kind));
            }
        }

        if ($count < \count($entries)) {
            throw InvalidCelebration::because(\sprintf('A group counts at least the %d entries it shows; %d is fewer.', \count($entries), $count));
        }

        $this->entries = $entries;
    }

    /**
     * The entry the group opens with: the earliest earned.
     */
    public function first(): Entry
    {
        return $this->entries[0];
    }

    /**
     * Whether the group holds a single entry, shown alone rather than as a group.
     */
    public function isSingle(): bool
    {
        return 1 === $this->count;
    }

    /**
     * How many entries the group holds beyond those it shows.
     */
    public function hidden(): int
    {
        return $this->count - \count($this->entries);
    }

    /**
     * The sequences of the entries shown, to mark them seen.
     *
     * @return non-empty-list<int>
     */
    public function sequences(): array
    {
        return array_map(static fn (Entry $entry): int => $entry->sequence, $this->entries);
    }
}
