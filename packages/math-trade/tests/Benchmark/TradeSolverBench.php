<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Benchmark;

use Generator;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

/**
 * A convention's trade: 1 000 offers from about 330 participants of three offers each, every offer wishing for 20 or
 * 100 others, one line in five a group of three, and one game for every two offers, so that copies and their guards
 * are common. Solving it must take a fraction of a second; reading it from JSON, a few tens of milliseconds.
 *
 * @BeforeMethods("setUp")
 */
final class TradeSolverBench
{
    private TradeList $list;

    private string $json = '';

    public function __construct()
    {
        $this->list = new TradeList([]);
    }

    /**
     * @param array{offers: int, wishes: int} $params
     */
    public function setUp(array $params): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(2026));
        $offers = $params['offers'];
        $byParticipant = [];

        for ($offer = 0; $offer < $offers; ++$offer) {
            $owner = intdiv($offer, 3);
            $wants = [];
            $seen = [];

            while (\count($seen) < $params['wishes']) {
                $line = [];

                for ($size = 0 === $random->getInt(0, 4) ? 3 : 1; $size > 0; --$size) {
                    $target = $random->getInt(0, $offers - 1);

                    if (intdiv($target, 3) !== $owner && !isset($seen[$target])) {
                        $seen[$target] = true;
                        $line[] = 'o'.$target;
                    }
                }

                if ([] !== $line) {
                    $wants[] = 1 === \count($line) ? new Want($line[0]) : new WantGroup($line);
                }
            }

            $byParticipant[$owner][] = new Offer('o'.$offer, $wants, 'g'.$random->getInt(0, intdiv($offers, 2)));
        }

        $this->list = new TradeList(array_map(
            static fn (int $owner, array $owned): Participant => new Participant('p'.$owner, $owned),
            array_keys($byParticipant),
            array_values($byParticipant),
        ));
        $this->json = new TradeListJson()->encode($this->list);
    }

    /**
     * @ParamProviders("sizes")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchSolve(): void
    {
        new TradeSolver()->solve($this->list, 'bench');
    }

    /**
     * @ParamProviders("sizes")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchDecode(): void
    {
        new TradeListJson()->decode($this->json);
    }

    /**
     * @return Generator<string, array{offers: int, wishes: int}>
     */
    public function sizes(): Generator
    {
        yield '1000 offers, 20 wishes' => ['offers' => 1_000, 'wishes' => 20];
        yield '1000 offers, 100 wishes' => ['offers' => 1_000, 'wishes' => 100];
    }
}
