<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit\Odds;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Tilttek\TavernGames\Contract\Simulation;
use Tilttek\TavernGames\Exception\InvalidTavernGame;
use Tilttek\TavernGames\Odds\Estimate;
use Tilttek\TavernGames\Odds\Estimator;
use Tilttek\TavernGames\Odds\Trial;

#[CoversClass(Estimator::class)]
#[CoversClass(Estimate::class)]
#[CoversClass(Trial::class)]
#[CoversClass(InvalidTavernGame::class)]
final class EstimatorTest extends TestCase
{
    public function testWinsTiesAndLossesAreCountedApart(): void
    {
        $estimate = Estimator::estimate($this->cycle([Trial::Win, Trial::Tie, Trial::Loss, Trial::Win]), 8, new Randomizer(new FacesEngine()));

        self::assertSame(['wins' => 4, 'ties' => 2, 'losses' => 2, 'samples' => 8], $estimate->toArray());
        self::assertSame(0.5, $estimate->winRate());
        self::assertSame(0.25, $estimate->tieRate());
        self::assertSame(0.25, $estimate->lossRate());
    }

    public function testTheBoundsOfTheSamples(): void
    {
        self::assertSame(1, Estimator::estimate($this->cycle([Trial::Loss]), 1, new Randomizer(new FacesEngine()))->losses);
        self::assertSame(100_000, Estimator::estimate($this->cycle([Trial::Tie]), 100_000, new Randomizer(new FacesEngine()))->ties);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refused(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [100_001];
    }

    #[DataProvider('refused')]
    public function testARefusedNumberOfSamples(int $samples): void
    {
        $this->expectException(InvalidTavernGame::class);
        $this->expectExceptionMessageIsOrContains('An estimate plays 1 to 100000 endings.');

        Estimator::estimate($this->cycle([Trial::Win]), $samples, new Randomizer(new FacesEngine()));
    }

    public function testAnEmptyEstimateHasNoRate(): void
    {
        $estimate = new Estimate(0, 0, 0);

        self::assertSame(0, $estimate->samples());
        self::assertSame(0.0, $estimate->winRate());
        self::assertSame(0.0, $estimate->tieRate());
        self::assertSame(0.0, $estimate->lossRate());
        self::assertSame(['win', 'tie', 'loss'], array_map(static fn (Trial $trial): string => $trial->value, Trial::cases()));
    }

    /**
     * @param non-empty-list<Trial> $trials
     */
    private function cycle(array $trials): Simulation
    {
        return new class($trials) implements Simulation {
            private int $next = 0;

            /**
             * @param non-empty-list<Trial> $trials
             */
            public function __construct(private readonly array $trials)
            {
            }

            public function trial(Randomizer $random): Trial
            {
                return $this->trials[$this->next++ % \count($this->trials)];
            }
        };
    }
}
