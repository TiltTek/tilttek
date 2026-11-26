<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

use Tilttek\GeekDates\Exception\InvalidQuery;

/**
 * Which entries a question reads: those with one of the tags, of one of the communities, verified only — each part
 * left out reads every entry.
 */
final readonly class Filter
{
    /**
     * @param list<string>|null $tags        one of these tags
     * @param list<string>|null $communities one of these communities
     */
    public function __construct(
        public ?array $tags = null,
        public ?array $communities = null,
        public bool $verifiedOnly = false,
    ) {
        if ([] === $tags || [] === $communities) {
            throw InvalidQuery::because('A filter on no tag or no community reads nothing: leave it out instead.');
        }
    }

    public function accepts(Entry $entry): bool
    {
        return (null === $this->tags || [] !== array_intersect($this->tags, $entry->tags))
            && (null === $this->communities || \in_array($entry->community, $this->communities, true))
            && (!$this->verifiedOnly || Status::Verified === $entry->status);
    }
}
