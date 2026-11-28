<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * What a page of the game shows: the entry, its details as corrected, the votes of the members beside them, and its
 * links to other entries.
 */
final readonly class Sheet
{
    /**
     * @param list<Link> $links from and to the entry
     */
    public function __construct(public Entry $entry, public PollResults $results, public array $links)
    {
    }

    public function details(): Details
    {
        return $this->entry->details();
    }

    /**
     * The links of a type, from or to the entry.
     *
     * @return list<Link>
     */
    public function linksOf(LinkType $type): array
    {
        return array_values(array_filter($this->links, static fn (Link $link): bool => $link->type === $type));
    }
}
