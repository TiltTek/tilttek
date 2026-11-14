<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\OpenSkill;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\OpenSkill\OpenSkillModel;
use Tilttek\Rating\OpenSkill\OpenSkillSystem;
use Tilttek\Rating\OpenSkill\SkillRating;
use Tilttek\Rating\Tests\Unit\Fixture\Current;

/**
 * OpenSkill against values computed by a separate implementation in Python, from the formulas of Weng and Lin alone.
 * Without the growth of the deviation, a duel of two new players gives the values the publication's method gives:
 * 27.635 and 22.365, with a deviation of 8.066.
 */
#[CoversClass(OpenSkillSystem::class)]
#[CoversClass(SkillRating::class)]
#[CoversClass(OpenSkillModel::class)]
final class OpenSkillSystemTest extends TestCase
{
    public function testADuelOfTwoNewPlayersWithoutGrowth(): void
    {
        $system = new OpenSkillSystem(tau: 0.0);
        $rated = $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob'), []);

        self::assertEquals(['ann' => new SkillRating(27_635_231, 8_065_506), 'bob' => new SkillRating(22_364_769, 8_065_506)], $rated);
    }

    public function testPlackettLuce(): void
    {
        $system = new OpenSkillSystem();

        self::assertEquals(
            ['ann' => new SkillRating(27_635_389, 8_065_901), 'bob' => new SkillRating(22_364_611, 8_065_901)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob'), []),
        );
        self::assertEquals(
            ['ann' => new SkillRating(30_650_512, 3_986_905), 'bob' => new SkillRating(22_829_818, 7_835_690), 'cy' => new SkillRating(19_661_827, 5_882_776)],
            $system->rate(GameResult::placings('r1', 'go', $this->at(), [['ann'], ['bob', 'cy']]), $this->three($system)),
        );
        self::assertEquals(
            ['ann' => new SkillRating(27_611_361, 2_991_421), 'bob' => new SkillRating(21_885_407, 6_876_021), 'cy' => new SkillRating(27_996_747, 8_063_800), 'dee' => new SkillRating(23_079_021, 4_942_981)],
            $system->rate($this->teams(), $this->four($system)),
        );
        self::assertEquals(
            ['ann' => new SkillRating(29_761_852, 4_949_690), 'bob' => new SkillRating(25_661_405, 7_932_690)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob', draw: true), Current::of($system, ['ann' => new SkillRating(30_000_000, 5_000_000)])),
        );
        self::assertSame('openskill-pl', $system->id());
    }

    public function testThurstoneMosteller(): void
    {
        $system = new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller);

        self::assertEquals(
            ['ann' => new SkillRating(29_230_971, 7_631_294), 'bob' => new SkillRating(20_769_029, 7_631_294)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob'), []),
        );
        self::assertEquals(
            ['ann' => new SkillRating(31_238_994, 3_892_270), 'bob' => new SkillRating(19_122_167, 5_396_338), 'cy' => new SkillRating(20_260_287, 5_285_125)],
            $system->rate(GameResult::placings('r1', 'go', $this->at(), [['ann'], ['bob', 'cy']]), $this->three($system)),
        );
        self::assertEquals(
            ['ann' => new SkillRating(27_309_203, 2_972_483), 'bob' => new SkillRating(20_241_361, 6_628_424), 'cy' => new SkillRating(30_326_647, 7_513_720), 'dee' => new SkillRating(23_917_934, 4_829_300)],
            $system->rate($this->teams(), $this->four($system)),
        );
        self::assertEquals(
            ['ann' => new SkillRating(29_032_118, 4_783_003), 'bob' => new SkillRating(27_688_082, 6_486_431)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob', draw: true), Current::of($system, ['ann' => new SkillRating(30_000_000, 5_000_000)])),
        );
        self::assertSame('openskill-tm', $system->id());
    }

    public function testAnUpsetFarBeyondAFloatStaysFinite(): void
    {
        $system = new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller);
        $current = Current::of($system, ['ann' => new SkillRating(900_000_000, 1_000_000), 'bob' => new SkillRating(1_000_000, 1_000_000)]);

        self::assertEquals(
            ['bob' => new SkillRating(25_644_518, 1_001_187), 'ann' => new SkillRating(875_355_482, 1_001_187)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'bob', 'ann'), $current),
        );
        $pl = new OpenSkillSystem();
        foreach ($pl->rate(GameResult::duel('r1', 'go', $this->at(), 'bob', 'ann'), Current::of($pl, ['ann' => new SkillRating(900_000_000, 1_000_000), 'bob' => new SkillRating(1_000_000, 1_000_000)])) as $rating) {
            self::assertInstanceOf(SkillRating::class, $rating);
            self::assertGreaterThan(0, $rating->sigma);
        }
    }

    public function testTwoStrongSidesAreRatedLikeTwoNewOnes(): void
    {
        $system = new OpenSkillSystem();
        $current = Current::of($system, ['ann' => new SkillRating(5_000_000_000, 8_333_333), 'bob' => new SkillRating(5_000_000_000, 8_333_333)]);

        // The same duel as two new players, whose skills are 25, moved by 5000 − 25: nothing overflows.
        self::assertEquals(
            ['ann' => new SkillRating(5_002_635_389, 8_065_901), 'bob' => new SkillRating(4_997_364_611, 8_065_901)],
            $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob'), $current),
        );
    }

    public function testKappaKeepsSomeUncertainty(): void
    {
        $system = new OpenSkillSystem(tau: 0.0, kappa: 0.99);
        $rated = $system->rate(GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob'), []);

        self::assertInstanceOf(SkillRating::class, $rated['ann']);
        self::assertSame((int) round(sqrt((25 / 3) ** 2 * 0.99) * 1_000_000), $rated['ann']->sigma);
    }

    public function testTheSettingsChangeTheRatings(): void
    {
        $result = GameResult::duel('r1', 'go', $this->at(), 'ann', 'bob');
        $base = new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, tau: 0.0);

        foreach ([
            new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, mu: 30.0, tau: 0.0),
            new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, sigma: 5.0, tau: 0.0),
            new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, beta: 2.0, tau: 0.0),
            new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, tau: 1.0),
            new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller, tau: 0.0, drawMargin: 2.0),
        ] as $other) {
            self::assertNotEquals($base->rate($result, []), $other->rate($result, []));
        }

        self::assertEquals(new SkillRating(30_000_000, 10_000_000), new OpenSkillSystem(mu: 30.0)->initial());
        self::assertEquals(new SkillRating(25_000_000, 5_000_000), new OpenSkillSystem(sigma: 5.0)->initial());
    }

    public function testTheScoreIsTheConservativeEstimate(): void
    {
        $skill = new SkillRating(27_635_389, 8_065_901);

        self::assertSame(344, new OpenSkillSystem()->score($skill));
        self::assertSame(1150, new OpenSkillSystem(z: 2.0)->score($skill));
        self::assertSame(2764, new OpenSkillSystem(z: 0.0)->score($skill));
        self::assertEqualsWithDelta(3.437686, $skill->conservative(), 1e-9);
        self::assertSame(27.635389, $skill->mean());
        self::assertSame(8.065901, $skill->deviation());
        self::assertSame(0, new OpenSkillSystem()->score(new OpenSkillSystem()->initial()));
        self::assertSame(-1500, new OpenSkillSystem(sigma: 10.0)->score(new SkillRating(15_000_000, 10_000_000)));
    }

    public function testSkillsAreWrittenAndReadBack(): void
    {
        $system = new OpenSkillSystem();

        self::assertSame(['mu' => 25, 'sigma' => 8], new SkillRating(25, 8)->toArray());
        self::assertEquals(new SkillRating(-25, 8), $system->restore(['mu' => -25, 'sigma' => 8]));
        self::assertEquals(new SkillRating(1, 1), SkillRating::of(0.000001, 0.0));

        foreach ([[], ['mu' => 1], ['sigma' => 1], ['mu' => '1', 'sigma' => 1], ['mu' => 1, 'sigma' => 1.0], ['mu' => 1, 'sigma' => 1, 'more' => 1]] as $values) {
            try {
                $system->restore($values);
                self::fail('Values were read as a skill.');
            } catch (InvalidRating $invalid) {
                self::assertSame('A skill is its mu and its sigma, whole numbers of millionths.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidRating::class);
        $this->expectExceptionMessageIsOrContains('The uncertainty of a skill is above 0.');

        $system->restore(['mu' => 1, 'sigma' => 0]);
    }

    public function testASkillIsProvisionalAboveTheDeviationAsked(): void
    {
        $system = new OpenSkillSystem(provisionalSigma: 2.5);

        self::assertTrue($system->isProvisional(new SkillRating(25_000_000, 2_500_001)));
        self::assertFalse($system->isProvisional(new SkillRating(25_000_000, 2_500_000)));
        self::assertTrue($system->isProvisional($system->initial()));
        self::assertFalse(new OpenSkillSystem()->isProvisional(new SkillRating(25_000_000, 8_000_000)));

        foreach ([0.0, -1.0] as $sigma) {
            try {
                new OpenSkillSystem(provisionalSigma: $sigma);
                self::fail('A threshold of '.$sigma.' was taken.');
            } catch (InvalidRating $invalidRating) {
                self::assertSame('The deviation above which a skill is provisional is above 0.', $invalidRating->getMessage());
            }
        }

        self::assertTrue(new OpenSkillSystem(provisionalSigma: 0.000001)->isProvisional(new SkillRating(0, 2)));
        $this->expectException(InvalidRating::class);
        $this->expectExceptionMessageIsOrContains('The OpenSkill system reads skills only.');
        $system->isProvisional(new EloRating(150_000));
    }

    public function testAnEloRatingIsNotReadAsASkill(): void
    {
        $this->expectException(InvalidRating::class);
        $this->expectExceptionMessageIsOrContains('The OpenSkill system reads skills only.');

        new OpenSkillSystem()->score(new EloRating(1));
    }

    public function testTheSettingsAreChecked(): void
    {
        $bad = [
            'The initial mean skill is 1 to 1000.' => [static fn (): OpenSkillSystem => new OpenSkillSystem(mu: 0.99), static fn (): OpenSkillSystem => new OpenSkillSystem(mu: 1000.01)],
            'The initial deviation of a skill is above 0 and at most its mean.' => [static fn (): OpenSkillSystem => new OpenSkillSystem(sigma: 0.0), static fn (): OpenSkillSystem => new OpenSkillSystem(sigma: 25.01)],
            'The deviation of a performance is above 0; the growth of a deviation and the draw margin are 0 or more.' => [static fn (): OpenSkillSystem => new OpenSkillSystem(beta: 0.0), static fn (): OpenSkillSystem => new OpenSkillSystem(tau: -0.01), static fn (): OpenSkillSystem => new OpenSkillSystem(drawMargin: -0.01)],
            'The smallest share of a variance kept is above 0 and below 1.' => [static fn (): OpenSkillSystem => new OpenSkillSystem(kappa: 0.0), static fn (): OpenSkillSystem => new OpenSkillSystem(kappa: 1.0)],
            'The conservative estimate takes 0 to 10 deviations off the mean.' => [static fn (): OpenSkillSystem => new OpenSkillSystem(z: -0.01), static fn (): OpenSkillSystem => new OpenSkillSystem(z: 10.01)],
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

        self::assertInstanceOf(OpenSkillSystem::class, new OpenSkillSystem(mu: 1.0, sigma: 1.0, tau: 0.0, kappa: 0.999, drawMargin: 0.0, z: 10.0));
        self::assertInstanceOf(OpenSkillSystem::class, new OpenSkillSystem(mu: 1000.0, kappa: 0.000001, z: 0.0));
    }

    /**
     * @return array<string, MemberRating>
     */
    private function three(OpenSkillSystem $system): array
    {
        return Current::of($system, ['ann' => new SkillRating(30_000_000, 4_000_000), 'bob' => new SkillRating(25_000_000, 8_333_333), 'cy' => new SkillRating(20_000_000, 6_000_000)]);
    }

    /**
     * @return array<string, MemberRating>
     */
    private function four(OpenSkillSystem $system): array
    {
        return Current::of($system, ['ann' => new SkillRating(28_000_000, 3_000_000), 'bob' => new SkillRating(24_000_000, 7_000_000), 'cy' => new SkillRating(25_000_000, 8_333_333), 'dee' => new SkillRating(22_000_000, 5_000_000)]);
    }

    private function teams(): GameResult
    {
        return new GameResult('r1', 'go', $this->at(), [['ann', 'bob'], ['cy', 'dee']], [2, 1]);
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 20:00:00 UTC');
    }
}
