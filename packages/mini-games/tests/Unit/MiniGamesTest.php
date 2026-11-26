<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\Game\BullsAndCows;
use Tilttek\MiniGames\Game\HigherOrLower;
use Tilttek\MiniGames\MiniGames;

#[CoversClass(MiniGames::class)]
#[CoversClass(InvalidMiniGame::class)]
final class MiniGamesTest extends TestCase
{
    public function testTheGamesByName(): void
    {
        $bulls = new BullsAndCows();
        $games = new MiniGames($bulls, new HigherOrLower());

        self::assertSame($bulls, $games->get('bulls-and-cows'));
        self::assertSame(['bulls-and-cows', 'higher-or-lower'], $games->names());
        self::assertSame(['bulls-and-cows', 'higher-or-lower'], $games->games()->names());
    }

    public function testAnUnknownGame(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('No mini-game is named "chess".');

        new MiniGames(new BullsAndCows())->get('chess');
    }

    public function testARefusalCarriesNoCode(): void
    {
        $refusal = InvalidMiniGame::because('No.');

        self::assertSame(0, $refusal->getCode());
        self::assertNull($refusal->getPrevious());
    }

    public function testTwoGamesOfOneName(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('Two mini-games are named "bulls-and-cows".');

        new MiniGames(new BullsAndCows(), new BullsAndCows());
    }
}
