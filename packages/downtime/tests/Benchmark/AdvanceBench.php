<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Benchmark;

use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Band;
use Tilttek\Downtime\Contract\Roller;
use Tilttek\Downtime\Contract\Roster;
use Tilttek\Downtime\Cost;
use Tilttek\Downtime\Downtime;
use Tilttek\Downtime\Outcome;
use Tilttek\Downtime\Resolution;
use Tilttek\Downtime\Rules\InMemoryRuleBook;
use Tilttek\Downtime\Store\InMemoryEntryStore;
use Tilttek\Downtime\SystemRules;

/**
 * What a game master does with the largest campaign one advance reads — 125 characters, each with a full queue of 8
 * activities, 1000 entries —: advance it by one day, the 1000 entries read, the first of each queue advanced and
 * the 125 changed kept at once; and the largest rules a system declares — 64 activities of 16 outcomes and 16
 * bands — checked whole.
 */
final readonly class AdvanceBench
{
    private Downtime $downtime;

    /** @var list<Activity> */
    private array $activities;

    public function __construct()
    {
        $this->activities = array_map(static fn (int $i): Activity => self::activity('activity-'.$i), range(1, SystemRules::MAX_ACTIVITIES));
        $roster = new class implements Roster {
            public function isGameMaster(string $campaign, string $member): bool
            {
                return true;
            }

            public function plays(string $campaign, string $character, string $member): bool
            {
                return true;
            }
        };
        $roller = new class implements Roller {
            public function roll(string $dice): int
            {
                return 11;
            }
        };

        $this->downtime = new Downtime(new InMemoryEntryStore(), new InMemoryRuleBook(new SystemRules('system-1', $this->activities)), $roster, $roller);

        for ($c = 0; $c < 125; ++$c) {
            for ($q = 0; $q < Downtime::MAX_QUEUE; ++$q) {
                $this->downtime->choose('entry-'.$c.'-'.$q, 'campaign-1', 'character-'.$c, 'system-1', 'activity-'.($q + 1), 'member-1');
            }
        }
    }

    private static function activity(string $key): Activity
    {
        $outcomes = array_map(static fn (int $i): Outcome => new Outcome('o'.$i, 'Outcome '.$i), range(1, Activity::MAX_OUTCOMES));
        $bands = array_map(static fn (int $i): Band => new Band($i, $i, 'o'.$i), range(1, Resolution::MAX_BANDS));

        return new Activity($key, 'Activity', Activity::MAX_DAYS, $outcomes, Resolution::rolled('1d16', array_reverse($bands)), [Cost::perDay('gp', 5)]);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchAdvanceTheLargestCampaign(): int
    {
        return \count($this->downtime->advance('campaign-1', 1, 'member-1'));
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchCheckTheLargestRules(): int
    {
        return \count(new SystemRules('system-1', $this->activities)->activities());
    }
}
