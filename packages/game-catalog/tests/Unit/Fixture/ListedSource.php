<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Fixture;

use Override;
use Tilttek\GameCatalog\Contract\EntrySource;
use Tilttek\GameCatalog\SourcedEntry;

/**
 * A source that sends the entries it is given, and counts how many were read.
 */
final class ListedSource implements EntrySource
{
    public int $read = 0;

    /**
     * @param list<SourcedEntry> $entries
     */
    public function __construct(private readonly string $name, private readonly array $entries)
    {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function entries(): iterable
    {
        foreach ($this->entries as $entry) {
            ++$this->read;

            yield $entry;
        }
    }
}
