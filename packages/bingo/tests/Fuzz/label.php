<?php

declare(strict_types=1);

/*
 * The fuzz target of the label of a printed card, run by `phil-dev fuzz`: any input is either refused with the
 * library's own exceptions or names a card whose label, written and read back, is the same; checking it against a game
 * with a printed batch either refuses it or finds the card of that serial.
 */
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Exception\BingoException;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\SerialLabel;
use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\WinPatterns;

require getenv('PHIL_DEV_AUTOLOAD');

$bingo = new Bingo(
    new InMemoryBingoStore(),
    new CardDealer(str_repeat('f', 32)),
    new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event;
        }
    },
    new class implements ClockInterface {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        }
    },
);
$grid = new Grid(3);
$bingo->create('fuzz', new SquarePool(array_map(static fn (int $n): Square => new Square('s'.$n, 'Square '.$n), range(1, 20))), $grid, WinPatterns::line($grid), TickMode::Called);
$bingo->print('fuzz', 5);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($bingo): void {
    try {
        $label = SerialLabel::parse($input);
    } catch (BingoException) {
        // The refusal the parser promises.
        return;
    }

    if (SerialLabel::parse($label->toString()) !== $label) {
        throw new LogicException('A label written and read back is another label.');
    }

    try {
        $claim = $bingo->verifyLabel('fuzz', $input);
    } catch (BingoException) {
        // A code that is not the card's, or a serial the game did not deal.
        return;
    }

    if ($claim->serial !== $label->serial) {
        throw new LogicException('A label was checked against another card.');
    }
});
