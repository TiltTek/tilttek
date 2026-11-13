<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use Phil\ContentWarnings\Display;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentLevel;

#[CoversClass(Boundary::class)]
#[CoversClass(ConsentLevel::class)]
final class BoundaryAndConsentTest extends TestCase
{
    public function testALineHidesAndAVeilFolds(): void
    {
        self::assertSame(Display::Hide, Boundary::Line->display());
        self::assertSame(Display::Fold, Boundary::Veil->display());
    }

    public function testALineIsStricterThanAVeilOnly(): void
    {
        self::assertTrue(Boundary::Line->isStricterThan(Boundary::Veil));
        self::assertFalse(Boundary::Veil->isStricterThan(Boundary::Line));
        self::assertFalse(Boundary::Line->isStricterThan(Boundary::Line));
        self::assertFalse(Boundary::Veil->isStricterThan(Boundary::Veil));
    }

    public function testEachLevelHasItsDisplay(): void
    {
        self::assertSame(Display::Show, ConsentLevel::Green->display());
        self::assertSame(Display::Fold, ConsentLevel::Yellow->display());
        self::assertSame(Display::Hide, ConsentLevel::Red->display());
    }

    public function testTheLevelsAreOrdered(): void
    {
        self::assertTrue(ConsentLevel::Red->isStricterThan(ConsentLevel::Yellow));
        self::assertTrue(ConsentLevel::Yellow->isStricterThan(ConsentLevel::Green));
        self::assertTrue(ConsentLevel::Red->isStricterThan(ConsentLevel::Green));
        self::assertFalse(ConsentLevel::Green->isStricterThan(ConsentLevel::Yellow));
        self::assertFalse(ConsentLevel::Yellow->isStricterThan(ConsentLevel::Yellow));
        self::assertFalse(ConsentLevel::Yellow->isStricterThan(ConsentLevel::Red));
    }

    public function testTheStrictestWins(): void
    {
        self::assertSame(ConsentLevel::Green, ConsentLevel::strictest());
        self::assertSame(ConsentLevel::Yellow, ConsentLevel::strictest(ConsentLevel::Green, ConsentLevel::Yellow, ConsentLevel::Green));
        self::assertSame(ConsentLevel::Red, ConsentLevel::strictest(ConsentLevel::Red, ConsentLevel::Yellow));
        self::assertSame(ConsentLevel::Red, ConsentLevel::strictest(ConsentLevel::Yellow, ConsentLevel::Red, ConsentLevel::Green));
    }
}
