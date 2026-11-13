<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use Phil\ContentWarnings\Display;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Exception\InvalidAnswer;

#[CoversClass(ConsentChecklist::class)]
#[CoversClass(InvalidAnswer::class)]
final class ConsentChecklistTest extends TestCase
{
    public function testThemesAreKeptInKeyOrder(): void
    {
        $checklist = ConsentChecklist::none()->with('torture', ConsentLevel::Red)->with('gore', ConsentLevel::Yellow);

        self::assertSame(['gore' => ConsentLevel::Yellow, 'torture' => ConsentLevel::Red], $checklist->all());
        self::assertSame(ConsentLevel::Red, $checklist->level('torture'));
        self::assertNull($checklist->level('spiders'));
        self::assertFalse($checklist->isEmpty());
        self::assertTrue(ConsentChecklist::none()->isEmpty());
    }

    public function testAThemeIsReplacedAndRemoved(): void
    {
        $checklist = ConsentChecklist::none()->with('gore', ConsentLevel::Green)->with('gore', ConsentLevel::Red);

        self::assertSame(['gore' => ConsentLevel::Red], $checklist->all());
        self::assertTrue($checklist->without('gore')->isEmpty());
        self::assertSame(['gore' => ConsentLevel::Red], $checklist->without('spiders')->all());
    }

    public function testItIsImmutable(): void
    {
        $none = ConsentChecklist::none();
        $gore = $none->with('gore', ConsentLevel::Red);
        $gore->without('gore');

        self::assertTrue($none->isEmpty());
        self::assertSame(ConsentLevel::Red, $gore->level('gore'));
    }

    public function testAnInvalidKeyIsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"no key" is not a theme key.');

        ConsentChecklist::none()->with('no key', ConsentLevel::Red);
    }

    public function testTheBoundIsKept(): void
    {
        $stored = [];

        for ($i = 0; $i < ConsentChecklist::MAX_THEMES; ++$i) {
            $stored['t'.$i] = 'green';
        }

        $checklist = ConsentChecklist::fromArray($stored);

        self::assertCount(ConsentChecklist::MAX_THEMES, $checklist->with('t1', ConsentLevel::Red)->all());

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A consent checklist names at most 2100 themes.');

        $checklist->with('one-more', ConsentLevel::Red);
    }

    public function testItStandsForPreferences(): void
    {
        $preferences = ConsentChecklist::none()->with('gore', ConsentLevel::Red)->with('spiders', ConsentLevel::Yellow)->with('torture', ConsentLevel::Green)->preferences();

        self::assertSame(Display::Show, $preferences->default);
        self::assertSame(Display::Hide, $preferences->theme('gore')?->display);
        self::assertSame(Display::Fold, $preferences->theme('spiders')?->display);
        self::assertSame(Display::Show, $preferences->theme('torture')?->display);
    }

    public function testItIsStoredAsAnArray(): void
    {
        $checklist = ConsentChecklist::none()->with('spiders', ConsentLevel::Yellow)->with('gore', ConsentLevel::Red);

        self::assertSame(['gore' => 'red', 'spiders' => 'yellow'], $checklist->toArray());
        self::assertEquals($checklist, ConsentChecklist::fromArray($checklist->toArray()));
    }

    public function testAStoredLevelMustBeKnown(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The theme "gore" has no consent level.');

        ConsentChecklist::fromArray(['gore' => 'blue']);
    }

    public function testAStoredLevelMustBeAText(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The theme "gore" has no consent level.');

        ConsentChecklist::fromArray(['gore' => null]);
    }
}
