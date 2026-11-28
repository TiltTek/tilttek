<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

use Tilttek\GameCatalog\SourcedEntry;

/**
 * A port that feeds the catalogue: the published books of an authoring tool, a list kept by the site, an open
 * database whose licence allows it. A source feeds the catalogue and is never its only copy: the catalogue keeps
 * every entry it was sent, and stays whole when the source is gone.
 */
interface EntrySource
{
    /**
     * The identifier of the source, which each of its entries keeps in its origin.
     */
    public function name(): string;

    /**
     * The entries the source sends, each under its own key; the catalogue reads at most the limit it is given.
     *
     * @return iterable<SourcedEntry>
     */
    public function entries(): iterable;
}
