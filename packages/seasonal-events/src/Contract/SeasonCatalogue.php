<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Contract;

use Tilttek\SeasonalEvents\Season;

/**
 * Where the seasons of the site come from: its code, its configuration or its database. Each question asks for them,
 * so that a season edited in a database is read as it is now.
 */
interface SeasonCatalogue
{
    /**
     * Every season, in the order the site gives them.
     *
     * @return list<Season>
     */
    public function all(): array;

    public function find(string $id): ?Season;
}
