<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\DailySeed;
use Tilttek\MiniGames\Day;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

#[CoversClass(DailySeed::class)]
#[CoversClass(InvalidMiniGame::class)]
final class DailySeedTest extends TestCase
{
    public function testTheSeedIsAnHmacOfTheGameAndTheDayUnderTheDerivedKey(): void
    {
        $key = hash_hkdf('sha256', str_repeat('s', 32), 32, 'tilttek_mini_games.daily_seed');
        $seed = new DailySeed(str_repeat('s', 32))->for('higher-or-lower', Day::fromString('2026-11-24'));

        self::assertSame('tilttek_mini_games.daily_seed', DailySeed::USAGE);
        self::assertSame(hash_hmac('sha256', "higher-or-lower\n2026-11-24", $key), $seed);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $seed);
    }

    public function testEachGameDayAndSecretHasItsOwnSeed(): void
    {
        $day = Day::fromString('2026-11-24');
        $seed = new DailySeed(str_repeat('s', 32));

        self::assertSame($seed->for('a', $day), new DailySeed(str_repeat('s', 32))->for('a', $day));
        self::assertNotSame($seed->for('a', $day), $seed->for('b', $day));
        self::assertNotSame($seed->for('a', $day), $seed->for('a', Day::fromString('2026-11-25')));
        self::assertNotSame($seed->for('a', $day), new DailySeed(str_repeat('t', 32))->for('a', $day));
    }

    public function testAShortSecretIsRefused(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('The secret of the daily games is at least 32 bytes.');

        new DailySeed(str_repeat('s', 31));
    }
}
