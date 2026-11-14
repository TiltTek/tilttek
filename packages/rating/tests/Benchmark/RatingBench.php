<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Benchmark;

use DateTimeImmutable;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Elo\KFactor;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\OpenSkill\OpenSkillModel;
use Tilttek\Rating\OpenSkill\OpenSkillSystem;
use Tilttek\Rating\Ratings;
use Tilttek\Rating\Store\InMemoryRatingStore;
use Tilttek\Rating\Tests\Unit\Fixture\ListSource;

/**
 * What a replay costs: the ratings of a game computed again from 10 000 results among 500 members — duels, games of
 * four and games of two teams of two, in turn —, by Elo, OpenSkill Plackett-Luce and OpenSkill Thurstone-Mosteller.
 */
final readonly class RatingBench
{
    private ListSource $source;

    public function __construct()
    {
        $results = [];
        $start = new DateTimeImmutable('2026-01-01 00:00:00 UTC');
        $member = static fn (int $game, int $seat): string => 'member-'.(($game * 7 + $seat * 131) % 500);

        for ($game = 0; $game < 10_000; ++$game) {
            $at = $start->modify('+'.$game.' minutes');
            $results[] = match ($game % 3) {
                0 => GameResult::duel('r'.$game, 'bench', $at, $member($game, 0), $member($game, 1), draw: 0 === $game % 11),
                1 => GameResult::placings('r'.$game, 'bench', $at, [[$member($game, 0)], [$member($game, 1), $member($game, 2)], [$member($game, 3)]]),
                default => new GameResult('r'.$game, 'bench', $at, [[$member($game, 0), $member($game, 1)], [$member($game, 2), $member($game, 3)]], [1 + $game % 2, 2 - $game % 2]),
            };
        }

        $this->source = new ListSource($results);
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchElo10000Results(): void
    {
        $this->replay(new EloSystem(KFactor::chess()));
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchOpenSkillPlackettLuce10000Results(): void
    {
        $this->replay(new OpenSkillSystem());
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchOpenSkillThurstoneMosteller10000Results(): void
    {
        $this->replay(new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller));
    }

    private function replay(RatingSystem $system): void
    {
        new Ratings(new InMemoryRatingStore(), $system, source: $this->source)->replay('bench');
    }
}
