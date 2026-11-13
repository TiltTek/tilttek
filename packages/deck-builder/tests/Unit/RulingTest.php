<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Format;
use Tilttek\DeckBuilder\Ruling;
use Tilttek\DeckBuilder\TabooList;

#[CoversClass(Ruling::class)]
final class RulingTest extends TestCase
{
    private Format $format;

    protected function setUp(): void
    {
        $this->format = new Format('open', 'Open', copies: 3, banned: ['old-ban'], restricted: ['one-of' => 1, 'two-of' => 2], tabooLists: [
            new TabooList('t1', new DateTimeImmutable('2026-06-01'), ['new-ban', 'old-ban'], ['two-of' => 1, 'free' => 2, 'loose' => 5]),
        ]);
    }

    public function testWithoutATabooListTheFormatsListsApply(): void
    {
        $ruling = $this->format->rulingOn(new DateTimeImmutable('2026-05-31'));

        self::assertNull($ruling->tabooList);
        self::assertSame('the format "open"', $ruling->bannedBy('old-ban'));
        self::assertNull($ruling->bannedBy('new-ban'));
        self::assertSame(0, $ruling->copyLimit(new Card('old-ban', 'Old')));
        self::assertSame(3, $ruling->copyLimit(new Card('new-ban', 'New')));
        self::assertSame(1, $ruling->copyLimit(new Card('one-of', 'One')));
        self::assertSame('the format "open"', $ruling->restrictedBy(new Card('one-of', 'One')));
        self::assertSame(2, $ruling->copyLimit(new Card('two-of', 'Two')));
        self::assertSame(3, $ruling->copyLimit(new Card('free', 'Free')));
        self::assertNull($ruling->restrictedBy(new Card('free', 'Free')));
    }

    public function testATabooListAddsToTheFormatsLists(): void
    {
        $ruling = $this->format->rulingOn(new DateTimeImmutable('2026-06-01'));

        self::assertSame('the format "open"', $ruling->bannedBy('old-ban'));
        self::assertSame('the taboo list "t1" from 2026-06-01', $ruling->bannedBy('new-ban'));
        self::assertSame(0, $ruling->copyLimit(new Card('new-ban', 'New', 5)));
        self::assertSame(1, $ruling->copyLimit(new Card('two-of', 'Two')));
        self::assertSame('the taboo list "t1" from 2026-06-01', $ruling->restrictedBy(new Card('two-of', 'Two')));
        self::assertSame(1, $ruling->copyLimit(new Card('one-of', 'One')));
        self::assertSame('the format "open"', $ruling->restrictedBy(new Card('one-of', 'One')));
        self::assertSame(2, $ruling->copyLimit(new Card('free', 'Free')));
    }

    public function testARestrictionNeverRaisesALimit(): void
    {
        $ruling = $this->format->rulingOn(new DateTimeImmutable('2026-07-01'));

        self::assertSame(3, $ruling->copyLimit(new Card('loose', 'Loose')));
        self::assertNull($ruling->restrictedBy(new Card('loose', 'Loose')));
        self::assertSame(1, $ruling->copyLimit(new Card('free', 'Free', 1)));
        self::assertNull($ruling->restrictedBy(new Card('free', 'Free', 1)));
        self::assertSame(2, $ruling->copyLimit(new Card('free', 'Free', 3)));
        self::assertNull($ruling->restrictedBy(new Card('free', 'Free', 2)));
        self::assertSame(5, $ruling->copyLimit(new Card('loose', 'Loose', 9)));
        self::assertSame('the taboo list "t1" from 2026-06-01', $ruling->restrictedBy(new Card('loose', 'Loose', 9)));
    }

    public function testACardsOwnLimitReplacesTheFormats(): void
    {
        $ruling = new Ruling(new Format('f', 'F', copies: 3));

        self::assertSame(30, $ruling->copyLimit(new Card('basic', 'Basic', 30)));
        self::assertSame(1, $ruling->copyLimit(new Card('unique', 'Unique', 1)));
        self::assertNull($ruling->bannedBy('basic'));
    }
}
