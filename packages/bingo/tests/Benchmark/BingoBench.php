<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Benchmark;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Activity\Activity;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\Trigger;
use Tilttek\Bingo\WinPattern;
use Tilttek\Bingo\WinPatterns;

/**
 * What a bingo costs: the layout of a card of 5 × 5 drawn from a pool of 500 squares, the check of every pattern of a
 * card against its ticks, and one gesture recorded for a member who holds a card — a convention's hot path.
 */
final class BingoBench
{
    private readonly BingoGame $game;

    private readonly CardDealer $dealer;

    private readonly WinPatterns $patterns;

    /** @var array<int, true> */
    private readonly array $ticked;

    private readonly Bingo $bingo;

    private int $gesture = 0;

    public function __construct()
    {
        $grid = new Grid();
        $pool = new SquarePool(array_map(static fn (int $n): Square => new Square('s'.$n, 'Square '.$n, new Trigger('booth.visited', 'booth:'.$n)), range(1, 500)));
        $this->patterns = new WinPatterns(WinPattern::row($grid), WinPattern::column($grid), WinPattern::diagonal($grid), WinPattern::fourCorners($grid), WinPattern::fullCard($grid));
        $this->game = new BingoGame('bench', $pool, $grid, $this->patterns);
        $this->dealer = new CardDealer(str_repeat('s', 32));
        $this->ticked = [0 => true, 6 => true, 12 => true, 18 => true, 3 => true];

        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-12 20:00:00 UTC');
            }
        };
        $events = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        $this->bingo = new Bingo(new InMemoryBingoStore(), $this->dealer, $events, $clock);
        $this->bingo->create('con', $pool, $grid, $this->patterns, TickMode::Activity);
        $this->bingo->deal('con', 'member:1');
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchLayoutOf5x5From500(): void
    {
        $this->dealer->layout($this->game, 42);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchCheckEveryPattern(): ?WinPattern
    {
        return $this->patterns->matched($this->ticked);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchRecordAGesture(): void
    {
        $this->bingo->record(Activity::of('k'.++$this->gesture, 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), 'booth:'.(1 + $this->gesture % 500)));
    }
}
