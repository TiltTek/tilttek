<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Benchmark;

use Tilttek\Sets\Evaluation;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Tier;

/**
 * What a set costs on a page: the largest set — 1 000 members and 20 tiers — measured against holdings of 5 000
 * members at level 20, every tier reached, and its definition read back from JSON.
 */
final class SetsBench
{
    private readonly SetDefinition $set;

    private readonly Holdings $holdings;

    private readonly string $json;

    /** The last evaluation, kept so that the measured call has an effect. */
    public ?Evaluation $evaluation = null;

    public function __construct()
    {
        $members = array_map(static fn (int $member): string => 'card-'.$member, range(1, 1000));
        $tiers = array_map(static fn (int $tier): Tier => new Tier('level-'.$tier, $tier), range(1, 20));
        $this->set = new SetDefinition('bench', 1, $members, $tiers);
        $this->json = $this->set->toJson();

        $levels = [];

        for ($member = 1; $member <= 5000; ++$member) {
            $levels['card-'.$member] = 20;
        }

        $this->holdings = Holdings::withLevels($levels);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchEvaluate1000MembersAnd20Tiers(): void
    {
        $this->evaluation = $this->set->evaluate($this->holdings);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReadADefinitionOf1000Members(): void
    {
        SetDefinition::fromJson($this->json);
    }
}
