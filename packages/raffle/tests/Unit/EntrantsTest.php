<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Entrants;

#[CoversClass(Entrants::class)]
final class EntrantsTest extends TestCase
{
    public function testTheEntrantsAreListedOnceInByteOrder(): void
    {
        self::assertSame(['B', 'a', 'a-1', 'b', '~'], Entrants::canonical(['b', 'a-1', '~', 'a', 'B', 'b']));
        self::assertSame(['10', '9'], Entrants::canonical(['9', '10']));
        self::assertSame([], Entrants::canonical([]));
    }

    public function testTheHashAndTheSeedAreTheSha256OfTheirCanonicalJson(): void
    {
        self::assertSame(hash('sha256', '["tilttek-raffle/1","door-prize",["a","b"]]'), Entrants::hash('door-prize', ['a', 'b']));
        self::assertSame(hash('sha256', '["tilttek-raffle/1","door-prize","h","r"]'), Entrants::seed('door-prize', 'h', 'r'));
    }
}
