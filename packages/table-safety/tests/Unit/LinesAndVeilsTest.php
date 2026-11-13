<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use Phil\ContentWarnings\Display;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\LinesAndVeils;

#[CoversClass(LinesAndVeils::class)]
#[CoversClass(InvalidAnswer::class)]
final class LinesAndVeilsTest extends TestCase
{
    public function testThemesAreKeptInKeyOrder(): void
    {
        $answers = LinesAndVeils::none()->with('torture', Boundary::Line)->with('gore', Boundary::Veil)->with('spiders', Boundary::Line);

        self::assertSame(['gore' => Boundary::Veil, 'spiders' => Boundary::Line, 'torture' => Boundary::Line], $answers->all());
        self::assertSame(['spiders', 'torture'], $answers->lines());
        self::assertSame(['gore'], $answers->veils());
        self::assertSame(Boundary::Line, $answers->boundary('torture'));
        self::assertNull($answers->boundary('clowns'));
        self::assertFalse($answers->isEmpty());
        self::assertTrue(LinesAndVeils::none()->isEmpty());
    }

    public function testAThemeIsReplacedAndRemoved(): void
    {
        $answers = LinesAndVeils::none()->with('gore', Boundary::Veil)->with('gore', Boundary::Line);

        self::assertSame(['gore' => Boundary::Line], $answers->all());
        self::assertTrue($answers->without('gore')->isEmpty());
        self::assertSame(['gore' => Boundary::Line], $answers->without('spiders')->all());
    }

    public function testItIsImmutable(): void
    {
        $none = LinesAndVeils::none();
        $gore = $none->with('gore', Boundary::Line);
        $gore->without('gore');

        self::assertTrue($none->isEmpty());
        self::assertSame(['gore'], $gore->lines());
    }

    public function testAnInvalidKeyIsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"Gore" is not a theme key.');

        LinesAndVeils::none()->with('Gore', Boundary::Line);
    }

    public function testTheBoundIsKept(): void
    {
        $stored = [];

        for ($i = 0; $i < LinesAndVeils::MAX_THEMES; ++$i) {
            $stored['t'.$i] = 'veil';
        }

        $answers = LinesAndVeils::fromArray($stored);

        self::assertCount(LinesAndVeils::MAX_THEMES, $answers->all());
        self::assertCount(LinesAndVeils::MAX_THEMES, $answers->with('t0', Boundary::Line)->all());

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('Lines and veils name at most 2100 themes.');

        $answers->with('one-more', Boundary::Line);
    }

    public function testTheyStandForPreferences(): void
    {
        $preferences = LinesAndVeils::none()->with('gore', Boundary::Line)->with('spiders', Boundary::Veil)->preferences();

        self::assertSame(Display::Show, $preferences->default);
        self::assertSame(Display::Hide, $preferences->theme('gore')?->display);
        self::assertSame(Display::Fold, $preferences->theme('spiders')?->display);
        self::assertNull($preferences->theme('torture'));
    }

    public function testTheyAreStoredAsAnArray(): void
    {
        $answers = LinesAndVeils::none()->with('spiders', Boundary::Veil)->with('gore', Boundary::Line);

        self::assertSame(['gore' => 'line', 'spiders' => 'veil'], $answers->toArray());
        self::assertEquals($answers, LinesAndVeils::fromArray($answers->toArray()));
        self::assertTrue(LinesAndVeils::fromArray([])->isEmpty());
    }

    public function testAStoredBoundaryMustBeKnown(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The theme "gore" has no boundary.');

        LinesAndVeils::fromArray(['gore' => 'wall']);
    }

    public function testAStoredBoundaryMustBeAText(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The theme "gore" has no boundary.');

        LinesAndVeils::fromArray(['gore' => 1]);
    }
}
