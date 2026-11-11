<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Progression\Curve;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Experience\ExperienceRule;
use Tilttek\Progression\Milestone;
use Tilttek\Progression\Subject;
use Tilttek\Progression\Track;

#[CoversClass(Track::class)]
#[CoversClass(Milestone::class)]
final class TrackTest extends TestCase
{
    public function testATrack(): void
    {
        $track = new Track('player', Curve::linear(100, 10), [
            new ExperienceRule('wins', ['game.won'], 30),
            new ExperienceRule('games', ['game.*'], 10),
        ], milestones: [new Milestone('veteran', 5, level: true), new Milestone('first', 50), new Milestone('thousand', 1000)]);

        self::assertSame('player', $track->name);
        self::assertSame(Subject::Member, $track->subject);
        self::assertNull($track->seasons);
        self::assertSame(['games', 'wins'], array_keys($track->rules));
        self::assertSame(['first', 'veteran', 'thousand'], array_keys($track->milestones));
        self::assertSame(['games', 'wins'], array_map(static fn (ExperienceRule $rule): string => $rule->name, $track->matching(Activity::of('k', 'member:1', 'game.won', new DateTimeImmutable()))));
        self::assertSame([], $track->matching(Activity::of('k', 'member:1', 'news.read', new DateTimeImmutable())));
        self::assertSame(['first', 'veteran'], $this->codes($track->milestonesBetween(0, 400)));
        self::assertSame(['veteran'], $this->codes($track->milestonesBetween(50, 400)));
        self::assertSame(['first'], $this->codes($track->milestonesBetween(49, 50)));
        self::assertSame([], $this->codes($track->milestonesBetween(400, 999)));
        self::assertSame(['thousand'], $this->codes($track->milestonesBetween(999, 1000)));
    }

    public function testAMilestone(): void
    {
        $curve = Curve::linear(100, 10);

        self::assertSame(400, new Milestone('veteran', 5, true)->threshold($curve));
        self::assertSame(1, new Milestone('first', 1)->threshold($curve));
        self::assertSame(2, new Milestone('two', 2, true)->value);
    }

    public function testAMilestoneLevelIsTwoOrMore(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The milestone "veteran" is at level 2 or more; 1 is not one.'));
        new Milestone('veteran', 1, true);
    }

    public function testAMilestoneAmountIsOneOrMore(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The milestone "first" is at 1 experience or more; 0 is not an amount.'));
        new Milestone('first', 0);
    }

    public function testAMilestoneCodeIsChecked(): void
    {
        $this->expectExceptionObject(InvalidProgression::name('milestone', 'First'));
        new Milestone('First', 1);
    }

    public function testAMilestoneIsOnTheCurve(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The curve has levels 1 to 10; 11 is not one of them.'));
        new Track('player', Curve::linear(100, 10), [], milestones: [new Milestone('beyond', 11, true)]);
    }

    public function testATrackNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidProgression::name('track', 'Player'));
        new Track('Player', Curve::linear(100), []);
    }

    public function testTwoRulesOfATrackHaveTwoNames(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The track "player" has two rules named "games".'));
        new Track('player', Curve::linear(100), [new ExperienceRule('games', ['a'], 1), new ExperienceRule('games', ['b'], 1)]);
    }

    public function testTwoMilestonesOfATrackHaveTwoCodes(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The track "player" has two milestones coded "first".'));
        new Track('player', Curve::linear(100), [], milestones: [new Milestone('first', 1), new Milestone('first', 2)]);
    }

    /**
     * @param list<Milestone> $milestones
     *
     * @return list<string>
     */
    private function codes(array $milestones): array
    {
        return array_map(static fn (Milestone $milestone): string => $milestone->code, $milestones);
    }
}
