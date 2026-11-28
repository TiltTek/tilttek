<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Outcome;
use Tilttek\TavernGames\Ending;
use Tilttek\TavernGames\Tone;

#[CoversClass(Ending::class)]
#[CoversClass(Tone::class)]
final class EndingTest extends TestCase
{
    public function testAVictoryAlone(): void
    {
        $ending = Ending::of(Outcome::won(['ann'], 'target-reached'), 'ann');

        self::assertSame(Tone::Victory, $ending->tone);
        self::assertTrue($ending->tone->celebrates());
        self::assertSame('target-reached', $ending->reason);
        self::assertSame(['ann'], $ending->winners);
    }

    public function testASharedVictory(): void
    {
        $ending = Ending::of(Outcome::won(['ann', 'bob'], 'tie'), 'bob');

        self::assertSame(Tone::Tie, $ending->tone);
        self::assertTrue($ending->tone->celebrates());
    }

    public function testAnotherEndingIsAGoodGame(): void
    {
        self::assertSame(Tone::GoodGame, Ending::of(Outcome::won(['ann'], 'target-reached'), 'bob')->tone);
        self::assertSame(Tone::GoodGame, Ending::of(Outcome::won(['ann'], 'target-reached'), null)->tone);
        self::assertSame(Tone::GoodGame, Ending::of(Outcome::ended('no-cargo'), 'ann')->tone);
        self::assertFalse(Tone::GoodGame->celebrates());
        self::assertSame(['victory', 'tie', 'good-game'], array_map(static fn (Tone $tone): string => $tone->value, Tone::cases()));
    }
}
