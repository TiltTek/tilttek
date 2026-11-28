<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TavernGames\Exception\InvalidTavernGame;
use Tilttek\TavernGames\Game\CrazyEights;
use Tilttek\TavernGames\Game\Pig;
use Tilttek\TavernGames\TavernGames;

#[CoversClass(TavernGames::class)]
#[CoversClass(InvalidTavernGame::class)]
final class TavernGamesTest extends TestCase
{
    public function testTheGamesByName(): void
    {
        $pig = new Pig();
        $games = new TavernGames($pig, new CrazyEights());

        self::assertSame($pig, $games->get('pig'));
        self::assertSame(['pig', 'crazy-eights'], $games->names());
        self::assertSame(['pig', 'crazy-eights'], $games->games()->names());
    }

    public function testAnUnknownGame(): void
    {
        $this->expectException(InvalidTavernGame::class);
        $this->expectExceptionMessageIsOrContains('No tavern game is named "chess".');

        new TavernGames(new Pig())->get('chess');
    }

    public function testARefusalCarriesNoCode(): void
    {
        $refusal = InvalidTavernGame::because('No.');

        self::assertSame(0, $refusal->getCode());
        self::assertNull($refusal->getPrevious());
    }

    public function testTwoGamesOfOneName(): void
    {
        $this->expectException(InvalidTavernGame::class);
        $this->expectExceptionMessageIsOrContains('Two tavern games are named "pig".');

        new TavernGames(new Pig(), new Pig());
    }
}
