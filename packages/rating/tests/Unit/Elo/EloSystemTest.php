<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Elo;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Elo\KFactor;
use Tilttek\Rating\Elo\KFactorStep;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\OpenSkill\SkillRating;
use Tilttek\Rating\Tests\Unit\Fixture\Current;

/**
 * The Elo ratings against values computed by a separate implementation in Python, from the formulas alone.
 */
#[CoversClass(EloSystem::class)]
#[CoversClass(EloRating::class)]
#[CoversClass(KFactor::class)]
#[CoversClass(KFactorStep::class)]
final class EloSystemTest extends TestCase
{
    public function testAnEloRatingIsNeverProvisionalByItself(): void
    {
        $system = new EloSystem();

        self::assertFalse($system->isProvisional($system->initial()));
        $this->expectException(InvalidRating::class);
        $this->expectExceptionMessageIsOrContains('The Elo system reads Elo ratings only.');
        $system->isProvisional(new SkillRating(25_000_000, 1));
    }

    public function testADuelOfTwoNewMembersMovesEachByHalfTheK(): void
    {
        $system = new EloSystem();
        $rated = $system->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob'), Current::of($system, ['ann' => $system->initial(), 'bob' => $system->initial()]));

        self::assertEquals(['ann' => new EloRating(151_600), 'bob' => new EloRating(148_400)], $rated);
        self::assertSame(1516.0, $this->elo($rated['ann'])->points());
        self::assertSame('elo', $system->id());
    }

    public function testADrawMovesTheStrongerDown(): void
    {
        $system = new EloSystem();
        $rated = $system->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob', draw: true), Current::of($system, ['ann' => new EloRating(160_000), 'bob' => new EloRating(140_000)]));

        self::assertEquals(['ann' => new EloRating(159_169), 'bob' => new EloRating(140_831)], $rated);
    }

    public function testAGameOfSeveralPlayersIsReadByPairs(): void
    {
        $system = new EloSystem(new KFactor(24));
        $result = GameResult::placings('r1', 'chess', $this->at(), [['ann'], ['bob', 'cy'], ['dee']]);
        $rated = $system->rate($result, Current::of($system, ['ann' => new EloRating(150_000), 'bob' => new EloRating(162_050), 'cy' => new EloRating(139_990), 'dee' => new EloRating(171_234)]));

        self::assertEquals(['ann' => new EloRating(151_439), 'bob' => new EloRating(161_795), 'cy' => new EloRating(140_613), 'dee' => new EloRating(169_426)], $rated);
    }

    public function testATeamGameMovesEachMemberWithTheirOwnK(): void
    {
        $system = new EloSystem(KFactor::chess());
        $result = new GameResult('r1', 'chess', $this->at(), [['ann', 'bob'], ['cy', 'dee']], [2, 1]);
        $current = Current::of($system, ['ann' => new EloRating(241_000), 'bob' => new EloRating(230_000), 'cy' => new EloRating(150_000), 'dee' => new EloRating(160_000)], ['ann' => 40, 'bob' => 40, 'cy' => 10, 'dee' => 40]);

        self::assertEquals(['ann' => new EloRating(240_010), 'bob' => new EloRating(228_019), 'cy' => new EloRating(153_962), 'dee' => new EloRating(161_981)], $system->rate($result, $current));
    }

    public function testTheFloorHoldsARating(): void
    {
        $system = new EloSystem(initial: 1000, floor: 1000);
        $result = GameResult::duel('r1', 'chess', $this->at(), 'bob', 'ann');

        self::assertEquals(['bob' => new EloRating(101_623), 'ann' => new EloRating(100_000)], $system->rate($result, Current::of($system, ['ann' => new EloRating(100_500), 'bob' => new EloRating(100_000)])));
        self::assertEquals(new EloRating(100_000), $system->initial());
    }

    public function testAMemberWithoutARatingStartsAtTheInitialOne(): void
    {
        $system = new EloSystem(initial: 1200, scale: 200);
        $rated = $system->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob'), []);

        self::assertEquals(['ann' => new EloRating(121_600), 'bob' => new EloRating(118_400)], $rated);
    }

    public function testAMemberWithoutARatingIsOnHerFirstGameForTheK(): void
    {
        $system = new EloSystem(new KFactor(10, new KFactorStep(40, beforeGames: 1)));
        $rated = $system->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob'), []);

        self::assertEquals(['ann' => new EloRating(152_000), 'bob' => new EloRating(148_000)], $rated);
    }

    public function testTheKStepOfARatingReadsItsPointsNotItsHundredths(): void
    {
        $system = new EloSystem(new KFactor(10, new KFactorStep(40, belowRating: 2410)), initial: 2400);
        $rated = $system->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob'), []);

        self::assertEquals(['ann' => new EloRating(242_000), 'bob' => new EloRating(238_000)], $rated);
    }

    public function testTheScaleSetsHowMuchADifferenceCounts(): void
    {
        $narrow = new EloSystem(scale: 200);
        $current = Current::of($narrow, ['ann' => new EloRating(170_000), 'bob' => new EloRating(150_000)]);

        // Expected 1 / (1 + 10^(−200/200)) = 10/11 for ann: 32 × (1 − 10/11) = 2.91.
        self::assertEquals(new EloRating(170_291), $narrow->rate(GameResult::duel('r1', 'chess', $this->at(), 'ann', 'bob'), $current)['ann']);
    }

    public function testTheKDecreasesStepByStep(): void
    {
        $k = KFactor::chess();

        self::assertSame(40, $k->for(0, 2600.0));
        self::assertSame(40, $k->for(29, 2600.0));
        self::assertSame(20, $k->for(30, 2399.99));
        self::assertSame(10, $k->for(30, 2400.0));
        self::assertSame(32, new KFactor()->for(0, 0.0));

        $both = new KFactor(5, new KFactorStep(50, beforeGames: 10, belowRating: 1000));
        self::assertSame(50, $both->for(9, 999.0));
        self::assertSame(5, $both->for(10, 999.0));
        self::assertSame(5, $both->for(9, 1000.0));
        self::assertCount(1, $both->steps);
        self::assertSame([0, 1], array_keys(new KFactor(5, ...['first' => new KFactorStep(50, 10), 'second' => new KFactorStep(20, 20)])->steps));
    }

    public function testRatingsAreWrittenAndReadBack(): void
    {
        $system = new EloSystem();

        self::assertSame(['hundredths' => 151_600], new EloRating(151_600)->toArray());
        self::assertEquals(new EloRating(-5), $system->restore(['hundredths' => -5]));
        self::assertSame(151_600, $system->score(new EloRating(151_600)));

        foreach ([[], ['hundredths' => '1'], ['hundredths' => 1, 'more' => 2]] as $values) {
            try {
                $system->restore($values);
                self::fail('Values were read as an Elo rating.');
            } catch (InvalidRating $invalid) {
                self::assertSame('An Elo rating is its hundredths, a whole number.', $invalid->getMessage());
            }
        }
    }

    public function testASkillIsNotReadAsAnEloRating(): void
    {
        $this->expectException(InvalidRating::class);
        $this->expectExceptionMessageIsOrContains('The Elo system reads Elo ratings only.');

        new EloSystem()->score(new SkillRating(1, 1));
    }

    public function testTheSettingsAreChecked(): void
    {
        $bad = [
            'The initial Elo rating is 0 to 10000.' => [static fn (): EloSystem => new EloSystem(initial: -1), static fn (): EloSystem => new EloSystem(initial: 10_001)],
            'The scale of the Elo rating is 1 to 10000.' => [static fn (): EloSystem => new EloSystem(scale: 0), static fn (): EloSystem => new EloSystem(scale: 10_001)],
            'The floor of the Elo rating is 0 to the initial rating.' => [static fn (): EloSystem => new EloSystem(floor: -1), static fn (): EloSystem => new EloSystem(floor: 1501)],
            'A K-factor is 1 to 1000.' => [static fn (): KFactor => new KFactor(0), static fn (): KFactor => new KFactor(1001), static fn (): KFactorStep => new KFactorStep(0, 1)],
            'A step of the K-factor applies before a number of games, below a rating, or both.' => [static fn (): KFactorStep => new KFactorStep(10)],
            'A step of the K-factor applies before 1 game or more.' => [static fn (): KFactorStep => new KFactorStep(10, 0)],
        ];

        foreach ($bad as $message => $builds) {
            foreach ($builds as $build) {
                try {
                    $build();
                    self::fail('A setting was taken: '.$message);
                } catch (InvalidRating $invalid) {
                    self::assertSame($message, $invalid->getMessage());
                }
            }
        }

        self::assertInstanceOf(EloSystem::class, new EloSystem(new KFactor(1000), 10_000, 10_000, 10_000));
        self::assertInstanceOf(EloSystem::class, new EloSystem(new KFactor(1), 0, 1, 0));
        self::assertSame(1, new KFactorStep(1, 1)->beforeGames);
    }

    private function elo(Rating $rating): EloRating
    {
        self::assertInstanceOf(EloRating::class, $rating);

        return $rating;
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 20:00:00 UTC');
    }
}
