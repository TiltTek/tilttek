<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\ArrayInput;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Format;
use Tilttek\DeckBuilder\TabooList;

#[CoversClass(Format::class)]
#[CoversClass(TabooList::class)]
#[CoversClass(ArrayInput::class)]
final class FormatTest extends TestCase
{
    public function testAFormatKeepsItsRules(): void
    {
        $format = new Format('open', 'Open play', 40, 60, 3, ['b', 'a'], ['c' => 1], [
            new TabooList('t2', new DateTimeImmutable('2026-10-01')),
            new TabooList('t1', new DateTimeImmutable('2026-01-01')),
        ]);

        self::assertSame('open', $format->code);
        self::assertSame('Open play', $format->name);
        self::assertSame(40, $format->minSize);
        self::assertSame(60, $format->maxSize);
        self::assertSame(3, $format->copies);
        self::assertSame(['b', 'a'], $format->banned);
        self::assertSame(['c' => 1], $format->restricted);
        self::assertSame(['t1', 't2'], array_map(static fn (TabooList $list): string => $list->code, $format->tabooLists));
    }

    public function testTheDefaultsAllowAnyDeck(): void
    {
        $format = new Format('casual', 'Casual');

        self::assertSame(1, $format->minSize);
        self::assertNull($format->maxSize);
        self::assertSame(Card::MAX_COPIES, $format->copies);
        self::assertSame([], $format->banned);
        self::assertSame([], $format->tabooLists);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $format = new Format('f', 'F', 0, 1, 1);
        self::assertSame(0, $format->minSize);
        self::assertSame(1, $format->maxSize);

        $format = new Format('f', 'F', Format::MAX_SIZE, Format::MAX_SIZE, Card::MAX_COPIES, restricted: ['a' => 999, 'b' => 1]);
        self::assertSame(Format::MAX_SIZE, $format->maxSize);
        self::assertSame(['a' => 999, 'b' => 1], $format->restricted);
        self::assertSame(5, new Format('f', 'F', 5, 5)->maxSize);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedFormats(): iterable
    {
        yield 'negative smallest deck' => [static fn (): Format => new Format('f', 'F', -1), 'The smallest deck of the format "f" is from 0 to 100000 cards; -1 is not.'];
        yield 'smallest deck too large' => [static fn (): Format => new Format('f', 'F', 100_001), 'The smallest deck of the format "f" is from 0 to 100000 cards; 100001 is not.'];
        yield 'largest below smallest' => [static fn (): Format => new Format('f', 'F', 40, 39), 'The largest deck of the format "f" is from 40 to 100000 cards; 39 is not.'];
        yield 'largest of zero' => [static fn (): Format => new Format('f', 'F', 0, 0), 'The largest deck of the format "f" is from 1 to 100000 cards; 0 is not.'];
        yield 'largest too large' => [static fn (): Format => new Format('f', 'F', 1, 100_001), 'The largest deck of the format "f" is from 1 to 100000 cards; 100001 is not.'];
        yield 'no copies' => [static fn (): Format => new Format('f', 'F', copies: 0), 'The copies of a card in the format "f" are from 1 to 999; 0 is not.'];
        yield 'too many copies' => [static fn (): Format => new Format('f', 'F', copies: 1000), 'The copies of a card in the format "f" are from 1 to 999; 1000 is not.'];
        yield 'banned twice' => [static fn (): Format => new Format('f', 'F', banned: ['a', 'a']), 'The format "f" lists the card "a" twice as banned.'];
        yield 'banned card refused' => [static fn (): Format => new Format('f', 'F', banned: ['']), 'The identifier of a card is'];
        yield 'restricted to none' => [static fn (): Format => new Format('f', 'F', restricted: ['a' => 0]), 'The format "f" restricts the card "a" to 1 to 999 copies; 0 is not.'];
        yield 'restricted too high' => [static fn (): Format => new Format('f', 'F', restricted: ['a' => 1000]), 'The format "f" restricts the card "a" to 1 to 999 copies; 1000 is not.'];
        yield 'restricted card refused' => [static fn (): Format => new Format('f', 'F', restricted: [' ' => 1]), 'The identifier of a card is'];
        yield 'two lists of one code' => [static fn (): Format => new Format('f', 'F', tabooLists: [new TabooList('t', new DateTimeImmutable('2026-01-01')), new TabooList('t', new DateTimeImmutable('2026-02-01'))]), 'The format "f" has two taboo lists "t".'];
        yield 'two lists of one day' => [static fn (): Format => new Format('f', 'F', tabooLists: [new TabooList('t', new DateTimeImmutable('2026-01-01')), new TabooList('u', new DateTimeImmutable('2026-01-01 18:00'))]), 'The format "f" has two taboo lists from 2026-01-01.'];
        yield 'too many lists' => [static fn (): Format => new Format('f', 'F', tabooLists: array_map(static fn (int $day): TabooList => new TabooList('t'.$day, new DateTimeImmutable('2026-01-01')->modify('+'.$day.' days')), range(0, 100))), 'The format "f" has at most 100 taboo lists.'];
        yield 'too many banned' => [static fn (): Format => new Format('f', 'F', banned: array_map(static fn (int $card): string => 'c'.$card, range(0, 10_000))), 'The format "f" bans at most 10000 cards.'];
        yield 'too many restricted' => [static fn (): Format => new Format('f', 'F', restricted: array_fill_keys(array_map(static fn (int $card): string => 'c'.$card, range(0, 10_000)), 1)), 'The format "f" restricts at most 10000 cards.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedFormats')]
    public function testRefusedRulesSayWhy(callable $build, string $message): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTenThousandCardsMayBeBannedAndRestricted(): void
    {
        $cards = array_map(static fn (int $card): string => 'c'.$card, range(1, 10_000));
        $format = new Format('f', 'F', banned: $cards, restricted: array_fill_keys($cards, 1));

        self::assertCount(10_000, $format->banned);
        self::assertCount(10_000, $format->restricted);
    }

    public function testOneHundredTabooListsAreAccepted(): void
    {
        $lists = array_map(static fn (int $day): TabooList => new TabooList('t'.$day, new DateTimeImmutable('2026-01-01')->modify('+'.$day.' days')), range(0, 99));

        self::assertCount(100, new Format('f', 'F', tabooLists: $lists)->tabooLists);
    }

    public function testTheTabooListOfADayIsTheLatestFromThatDayOrBefore(): void
    {
        $format = new Format('f', 'F', tabooLists: [
            new TabooList('autumn', new DateTimeImmutable('2026-10-01')),
            new TabooList('spring', new DateTimeImmutable('2026-04-01')),
        ]);

        self::assertNull($format->tabooListOn(new DateTimeImmutable('2026-03-31 23:59')));
        self::assertSame('spring', $format->tabooListOn(new DateTimeImmutable('2026-04-01 00:00'))?->code);
        self::assertSame('spring', $format->tabooListOn(new DateTimeImmutable('2026-09-30'))?->code);
        self::assertSame('autumn', $format->tabooListOn(new DateTimeImmutable('2026-10-01'))?->code);
        self::assertSame('autumn', $format->tabooListOn(new DateTimeImmutable('2027-10-01'))?->code);
        self::assertSame('autumn', $format->rulingOn(new DateTimeImmutable('2026-12-01'))->tabooList?->code);
        self::assertSame($format, $format->rulingOn(new DateTimeImmutable('2026-12-01'))->format);
    }

    public function testTheDayOfAMomentIsTakenInItsOwnTimeZone(): void
    {
        $format = new Format('f', 'F', tabooLists: [new TabooList('t', new DateTimeImmutable('2026-10-01'))]);

        // 2026-09-30 22:00 in Montréal is 2026-10-01 02:00 UTC: the day is still the 30th.
        self::assertNull($format->tabooListOn(new DateTimeImmutable('2026-09-30 22:00', new DateTimeZone('America/Toronto'))));
        self::assertSame('2026-10-01T00:00:00+00:00', new TabooList('t', new DateTimeImmutable('2026-10-01 23:30', new DateTimeZone('Asia/Tokyo')))->effectiveFrom->format('c'));
    }

    public function testAFormatWritesAndReadsBack(): void
    {
        $format = new Format('open', 'Open « play »', 40, null, 3, ['x/1'], ['0' => 1, 'y' => 2], [
            new TabooList('t1', new DateTimeImmutable('2026-01-01'), ['z'], ['w' => 1]),
        ]);

        $json = $format->toJson();

        self::assertSame('{"code":"open","name":"Open « play »","min_size":40,"max_size":null,"copies":3,"banned":["x/1"],"restricted":{"0":1,"y":2},"taboo_lists":[{"code":"t1","effective_from":"2026-01-01","banned":["z"],"restricted":{"w":1}}]}', $json);
        self::assertTrue(Format::fromJson($json)->equals($format));
        self::assertTrue(Format::fromArray($format->toArray())->equals($format));
        self::assertFalse(new Format('open', 'Other')->equals($format));
    }

    public function testAMinimalFormatReads(): void
    {
        $format = Format::fromJson('{"code":"f","name":"F"}');

        self::assertSame(1, $format->minSize);
        self::assertSame(999, $format->copies);
        self::assertNull($format->maxSize);
        self::assertTrue(Format::fromJson('{"code":"f","name":"F","restricted":[1]}')->equals(new Format('f', 'F', restricted: ['0' => 1])));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['{', 'A format in JSON does not read: Syntax error'];
        yield 'too deep' => [str_repeat('[', 16).str_repeat(']', 16), 'A format in JSON does not read: Maximum stack depth exceeded'];
        yield 'nested as deep as allowed' => [str_repeat('[', 15).str_repeat(']', 15), 'A format is an object of keys.'];
        yield 'a list' => ['[1]', 'A format is an object of keys.'];
        yield 'an empty object' => ['[]', 'A format needs its "code".'];
        yield 'a long unknown key' => ['{"code":"f","name":"F","'.str_repeat('k', 70).'":1}', 'A format has no key "'.str_repeat('k', 64).'".'];
        yield 'a number' => ['1', 'A format is an object of keys.'];
        yield 'an unknown key' => ['{"code":"f","name":"F","colour":1}', 'A format has no key "colour".'];
        yield 'no name' => ['{"code":"f"}', 'A format needs its "name".'];
        yield 'a code of a number' => ['{"code":1,"name":"F"}', 'The "code" of a format is a string.'];
        yield 'a size of a string' => ['{"code":"f","name":"F","min_size":"40"}', 'The "min_size" of a format is an integer.'];
        yield 'a largest size of a string' => ['{"code":"f","name":"F","max_size":"40"}', 'The "max_size" of a format is an integer or null.'];
        yield 'banned not a list' => ['{"code":"f","name":"F","banned":{"a":"b"}}', 'The "banned" of a format is a list.'];
        yield 'banned not strings' => ['{"code":"f","name":"F","banned":[1]}', 'The "banned" of a format are strings.'];
        yield 'restricted not an object' => ['{"code":"f","name":"F","restricted":"a"}', 'The "restricted" of a format is an object of integers.'];
        yield 'restricted not integers' => ['{"code":"f","name":"F","restricted":{"a":"1"}}', 'The "restricted" of a format is an object of integers.'];
        yield 'taboo lists not a list' => ['{"code":"f","name":"F","taboo_lists":{"a":1}}', 'The "taboo_lists" of a format is a list.'];
        yield 'taboo list not an object' => ['{"code":"f","name":"F","taboo_lists":[1]}', 'A taboo list is an object of keys.'];
        yield 'taboo list day refused' => ['{"code":"f","name":"F","taboo_lists":[{"code":"t","effective_from":"2026-02-30"}]}', 'The "effective_from" of a taboo list is a day such as 2026-11-01; "2026-02-30" is not.'];
        yield 'taboo list day of another shape' => ['{"code":"f","name":"F","taboo_lists":[{"code":"t","effective_from":"1 Jan 2026"}]}', 'The "effective_from" of a taboo list is a day such as 2026-11-01; "1 Jan 2026" is not.'];
        yield 'taboo list day with a time' => ['{"code":"f","name":"F","taboo_lists":[{"code":"t","effective_from":"2026-01-01T00:00"}]}', 'is not.'];
        yield 'taboo list day not a string' => ['{"code":"f","name":"F","taboo_lists":[{"code":"t","effective_from":20260101}]}', 'The "effective_from" of a taboo list is a string.'];
        yield 'taboo list unknown key' => ['{"code":"f","name":"F","taboo_lists":[{"code":"t","effective_from":"2026-01-01","x":1}]}', 'A taboo list has no key "x".'];
    }

    #[DataProvider('refusedJson')]
    public function testJsonThatDoesNotReadIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains($message);

        Format::fromJson($json);
    }

    public function testJsonOfMoreThanOneMebibyteIsRefused(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A format in JSON has at most 1048576 bytes.');

        Format::fromJson('"'.str_repeat('a', 1_048_575).'"');
    }

    public function testJsonOfOneMebibyteIsRead(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A format is an object of keys.');

        Format::fromJson('"'.str_repeat('a', 1_048_574).'"');
    }

    public function testATabooListReadsAndWritesBack(): void
    {
        $list = TabooList::fromArray(['code' => 't', 'effective_from' => '2026-02-28', 'banned' => ['a'], 'restricted' => ['b' => 2]]);

        self::assertSame(['code' => 't', 'effective_from' => '2026-02-28', 'banned' => ['a'], 'restricted' => ['b' => 2]], $list->toArray());
        self::assertSame('UTC', $list->effectiveFrom->getTimezone()->getName());
        self::assertSame([], TabooList::fromArray(['code' => 't', 'effective_from' => '2026-02-28'])->banned);
    }

    public function testALongRefusedDayIsCutInTheMessage(): void
    {
        $day = str_repeat('9', 40);

        try {
            TabooList::fromArray(['code' => 't', 'effective_from' => $day]);
            self::fail('A refused day was accepted.');
        } catch (InvalidDeck $invalidDeck) {
            self::assertSame('The "effective_from" of a taboo list is a day such as 2026-11-01; "'.substr($day, 0, 32).'" is not.', $invalidDeck->getMessage());
        }
    }

    public function testATabooListChecksItsCards(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The taboo list "t" lists the card "a" twice as banned.');

        new TabooList('t', new DateTimeImmutable(), ['a', 'a']);
    }

    public function testATabooListChecksItsRestrictions(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The taboo list "t" restricts the card "a" to 1 to 999 copies; 0 is not.');

        new TabooList('t', new DateTimeImmutable(), [], ['a' => 0]);
    }
}
