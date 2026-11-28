<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Provenance;

/**
 * One page of results, best first, with the attribution of every source the page shows.
 */
final readonly class SearchResults
{
    /**
     * @param list<RuleHit> $hits  the page, best first
     * @param int           $total how many rules answer the query, on every page
     */
    public function __construct(
        public RuleQuery $query,
        public array $hits,
        public int $total,
        private AttributionRenderer $renderer = new AttributionRenderer(),
    ) {
    }

    /**
     * The sources of the rules of the page, each once, in the order they first appear.
     *
     * @return list<Provenance>
     */
    public function sources(): array
    {
        return $this->renderer->sources(array_map(static fn (RuleHit $hit): Provenance => $hit->provenance(), $this->hits), all: true);
    }

    /**
     * The notices of the page, one line for each source.
     */
    public function attributionText(): string
    {
        return $this->renderer->text($this->sources(), all: true);
    }

    /**
     * The notices of the page as an escaped HTML list; an empty string for a page without results.
     */
    public function attributionHtml(): string
    {
        return $this->renderer->html($this->sources(), all: true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->hits;
    }

    /**
     * Whether a page follows this one.
     */
    public function hasMore(): bool
    {
        return $this->query->offset + \count($this->hits) < $this->total;
    }
}
