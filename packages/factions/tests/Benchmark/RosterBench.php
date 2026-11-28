<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Benchmark;

use Symfony\Component\Clock\MockClock;
use Tilttek\Factions\Faction;
use Tilttek\Factions\Factions;
use Tilttek\Factions\Membership;
use Tilttek\Factions\Rank;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Standing;
use Tilttek\Factions\Store\InMemoryRosterStore;
use Tilttek\Factions\Subject;

/**
 * What a game master and the players do with the largest roster the library takes — 64 factions, each with 16 ranks,
 * a standing with the party and 63 characters and 64 members, and 512 relations —: open it, move a standing, each
 * change checking the whole roster anew.
 */
final class RosterBench
{
    private readonly Factions $factions;

    private readonly Reason $reason;

    private int $next = 0;

    public function __construct()
    {
        $ranks = array_map(static fn (int $r): Rank => new Rank('r'.$r, 'Rank '.$r), range(1, Faction::MAX_RANKS));
        $standings = [new Standing(Subject::party(), 1)];
        $members = [];

        for ($c = 1; $c < Faction::MAX_STANDINGS; ++$c) {
            $standings[] = new Standing(Subject::character('c'.$c), $c % 7 - 3);
        }

        for ($c = 1; $c <= Faction::MAX_MEMBERS; ++$c) {
            $members[] = new Membership('c'.$c, 'r'.($c % Faction::MAX_RANKS + 1));
        }

        $factions = [];
        for ($f = 1; $f <= Roster::MAX_FACTIONS; ++$f) {
            $factions[] = new Faction('f'.$f, 'Faction '.$f, str_repeat('A faction of the city. ', 150), $ranks, $standings, $members, 0 === $f % 5);
        }

        $relations = [];
        for ($i = 1; \count($relations) < Roster::MAX_RELATIONS; ++$i) {
            for ($j = $i + 1; $j <= Roster::MAX_FACTIONS && \count($relations) < Roster::MAX_RELATIONS; ++$j) {
                $relations[] = new Relation('f'.$i, 'f'.$j, Stance::cases()[($i + $j) % 6], 'Old feud.', 0 === $j % 3);
            }
        }

        $this->factions = new Factions(new InMemoryRosterStore(), new MockClock('2026-11-25 20:00:00', 'UTC'));
        $this->factions->create(new Roster('campaign-1', 'member-1', Scale::standard(), $factions, $relations));

        $this->reason = new Reason('Bench', 'session-1');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchGameMasterView(): int
    {
        return \count($this->factions->view('campaign-1', 'member-1')->toArray()['factions']);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchPlayerView(): int
    {
        return \count($this->factions->playerView('campaign-1', 'c7')->toArray()['relations']);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchShiftAStanding(): int
    {
        $steps = 0 === $this->next++ % 2 ? 1 : -1;

        return $this->factions->shift('campaign-1', 'f64', Subject::character('c63'), $steps, $this->reason, 'member-1')->version;
    }
}
