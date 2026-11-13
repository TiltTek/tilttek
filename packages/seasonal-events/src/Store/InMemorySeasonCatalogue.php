<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Store;

use Override;
use Tilttek\SeasonalEvents\Contract\SeasonCatalogue;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;
use Tilttek\SeasonalEvents\Season;

/**
 * Seasons given once, in code or read from a configuration: they never change while the process lives.
 */
final readonly class InMemorySeasonCatalogue implements SeasonCatalogue
{
    /** @var array<string, Season> */
    private array $seasons;

    /**
     * @param list<Season> $seasons
     */
    public function __construct(array $seasons = [])
    {
        $byId = [];

        foreach ($seasons as $season) {
            if (isset($byId[$season->id])) {
                throw InvalidSeason::because(\sprintf('Two seasons are named "%s".', $season->id));
            }

            $byId[$season->id] = $season;
        }

        $this->seasons = $byId;
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->seasons);
    }

    #[Override]
    public function find(string $id): ?Season
    {
        return $this->seasons[$id] ?? null;
    }
}
