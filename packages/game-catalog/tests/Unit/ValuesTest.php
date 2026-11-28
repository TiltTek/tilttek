<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Erratum;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Experience;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Gtin;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\LanguageDependence;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\LinkType;
use Tilttek\GameCatalog\Origin;
use Tilttek\GameCatalog\Range;

#[CoversClass(Range::class)]
#[CoversClass(IdentifierScheme::class)]
#[CoversClass(ExternalId::class)]
#[CoversClass(Gtin::class)]
#[CoversClass(Origin::class)]
#[CoversClass(Erratum::class)]
#[CoversClass(Link::class)]
#[CoversClass(LinkType::class)]
#[CoversClass(LanguageDependence::class)]
#[CoversClass(Experience::class)]
#[CoversClass(InvalidEntry::class)]
final class ValuesTest extends TestCase
{
    public function testARangeHoldsBothEnds(): void
    {
        $range = new Range(2, 5);

        self::assertSame(2, $range->min);
        self::assertSame(5, $range->max);
        self::assertTrue($range->contains(2));
        self::assertTrue($range->contains(5));
        self::assertFalse($range->contains(1));
        self::assertFalse($range->contains(6));
        self::assertTrue($range->equals(new Range(2, 5)));
        self::assertFalse($range->equals(new Range(2, 4)));
        self::assertFalse($range->equals(new Range(3, 5)));
        self::assertTrue(Range::exactly(4)->equals(new Range(4, 4)));
        self::assertSame($range, $range->within(2, 5, 'numbers of players'));
        self::assertSame(0, new Range(0, Range::MAX)->min);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badRanges(): iterable
    {
        yield 'under 0' => [-1, 3];
        yield 'over the maximum' => [1, Range::MAX + 1];
        yield 'reversed' => [4, 3];
    }

    #[DataProvider('badRanges')]
    public function testARangeIsOrderedAndBounded(int $min, int $max): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('A range is from 0 to 100000, its minimum at most its maximum.');
        new Range($min, $max);
    }

    /**
     * @return iterable<string, array{Range}>
     */
    public static function rangesOutOfAField(): iterable
    {
        yield 'under the floor' => [new Range(0, 4)];
        yield 'over the ceiling' => [new Range(1, 9)];
    }

    #[DataProvider('rangesOutOfAField')]
    public function testARangeIsWithinTheBoundsOfItsField(Range $range): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('The numbers of players are from 1 to 8.');
        $range->within(1, 8, 'numbers of players');
    }

    public function testAnIdentifierOfADatabaseLinksToItsPage(): void
    {
        self::assertSame('https://boardgamegeek.com/boardgame/13', ExternalId::boardGameGeek(13)->link());
        self::assertSame('https://rpggeek.com/rpgitem/42', ExternalId::rpgGeek('42')->link());
        self::assertSame('https://www.wikidata.org/wiki/Q17271', ExternalId::wikidata('Q17271')->link());
        self::assertSame('rpggeek:42', ExternalId::rpgGeek(42)->key());
        self::assertSame('wikidata:Q1', ExternalId::wikidata('Q1')->key());
        self::assertSame('999999999999', ExternalId::boardGameGeek('999999999999')->value);
        self::assertTrue(ExternalId::rpgGeek(42)->equals(ExternalId::rpgGeek('42')));
        self::assertFalse(ExternalId::rpgGeek(42)->equals(ExternalId::boardGameGeek(42)));
        self::assertFalse(ExternalId::rpgGeek(42)->equals(ExternalId::rpgGeek(43)));
    }

    /**
     * @return iterable<string, array{IdentifierScheme, string}>
     */
    public static function badExternalIds(): iterable
    {
        yield 'empty' => [IdentifierScheme::BoardGameGeek, ''];
        yield 'a leading zero' => [IdentifierScheme::BoardGameGeek, '013'];
        yield 'zero' => [IdentifierScheme::RpgGeek, '0'];
        yield 'thirteen digits' => [IdentifierScheme::RpgGeek, '1234567890123'];
        yield 'a letter' => [IdentifierScheme::BoardGameGeek, '13a'];
        yield 'a line break after' => [IdentifierScheme::BoardGameGeek, "13\n"];
        yield 'a Wikidata item in a geek' => [IdentifierScheme::BoardGameGeek, 'Q13'];
        yield 'digits in Wikidata' => [IdentifierScheme::Wikidata, '13'];
        yield 'a property in Wikidata' => [IdentifierScheme::Wikidata, 'P31'];
        yield 'a leading zero in Wikidata' => [IdentifierScheme::Wikidata, 'Q013'];
        yield 'thirteen digits in Wikidata' => [IdentifierScheme::Wikidata, 'Q1234567890123'];
        yield 'a line break after in Wikidata' => [IdentifierScheme::Wikidata, "Q13\n"];
    }

    #[DataProvider('badExternalIds')]
    public function testAnIdentifierOfADatabaseHasItsShape(IdentifierScheme $scheme, string $value): void
    {
        self::assertFalse($scheme->accepts($value));
        $this->expectException(InvalidEntry::class);
        new ExternalId($scheme, $value);
    }

    public function testTheRefusalOfAnIdentifierShowsItShortened(): void
    {
        $this->expectExceptionMessageIs('"'.str_repeat('9', 39).'…" is not an identifier of boardgamegeek.');
        ExternalId::boardGameGeek(str_repeat('9', 50));
    }

    public function testTheRefusalOfAShortIdentifierShowsItWhole(): void
    {
        $this->expectExceptionMessageIs('"x" is not an identifier of wikidata.');
        ExternalId::wikidata('x');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function barcodes(): iterable
    {
        yield 'an EAN-13' => ['4006381333931', '04006381333931'];
        yield 'a UPC-A' => ['036000291452', '00036000291452'];
        yield 'an EAN-8' => ['73513537', '00000073513537'];
        yield 'a GTIN-14' => ['10614141000415', '10614141000415'];
        yield 'an ISBN-13 with hyphens' => ['978-2-07-036822-8', '09782070368228'];
        yield 'spaces' => ['4006381 333931', '04006381333931'];
        yield 'a check digit of 0' => ['0000000000000', '00000000000000'];
    }

    #[DataProvider('barcodes')]
    public function testABarcodeIsKeptAsFourteenDigits(string $code, string $value): void
    {
        self::assertSame($value, new Gtin($code)->value);
    }

    public function testTheSameBoxScannedTwoWaysIsTheSameBarcode(): void
    {
        self::assertTrue(new Gtin('036000291452')->equals(new Gtin('0036000291452')));
        self::assertFalse(new Gtin('036000291452')->equals(new Gtin('4006381333931')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badShapes(): iterable
    {
        yield 'empty' => [''];
        yield 'seven digits' => ['1234567'];
        yield 'nine digits' => ['123456789'];
        yield 'eleven digits' => ['12345678901'];
        yield 'fifteen digits' => ['123456789012345'];
        yield 'a letter' => ['400638133393X'];
        yield 'a line break after' => ["4006381333931\n"];
    }

    #[DataProvider('badShapes')]
    public function testABarcodeHasEightTwelveThirteenOrFourteenDigits(string $code): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('A barcode is a GTIN of 8, 12, 13 or 14 digits.');
        new Gtin($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCheckDigits(): iterable
    {
        yield 'an EAN-13' => ['4006381333932'];
        yield 'a UPC-A' => ['036000291453'];
        yield 'an EAN-8' => ['73513538'];
        yield 'a GTIN-14' => ['10614141000416'];
        yield 'a weight on the first digit' => ['20614141000415'];
        yield 'a weight on the second digit' => ['11614141000415'];
    }

    #[DataProvider('badCheckDigits')]
    public function testTheCheckDigitOfABarcodeIsVerified(string $code): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('The check digit of the barcode is wrong.');
        new Gtin($code);
    }

    public function testAnOriginIsManualOrASourceAndItsKey(): void
    {
        $manual = Origin::manual();
        $sourced = Origin::source('books', 'b-12');

        self::assertTrue($manual->isManual());
        self::assertNull($manual->key());
        self::assertNull($manual->source);
        self::assertFalse($sourced->isManual());
        self::assertSame('books/b-12', $sourced->key());
        self::assertSame('books', $sourced->source);
        self::assertSame('b-12', $sourced->key);
    }

    public function testTheSourceOfAnOriginIsAnIdentifier(): void
    {
        $this->expectExceptionMessageIs('The identifier of a source is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        Origin::source('1books', 'b-12');
    }

    public function testTheKeyOfAnOriginIsAnIdentifier(): void
    {
        $this->expectExceptionMessageIs('The identifier of an entry of a source is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        Origin::source('books', 'b 12');
    }

    public function testAnErratumKeepsItsTextTrimmed(): void
    {
        $at = new DateTimeImmutable('2027-01-10 12:00 UTC');
        $erratum = new Erratum('e-1', ' p. 42 ', "The spell lasts one round.\r\nNot ten.", $at);

        self::assertSame('e-1', $erratum->id);
        self::assertSame('p. 42', $erratum->location);
        self::assertSame("The spell lasts one round.\nNot ten.", $erratum->text);
        self::assertSame($at, $erratum->publishedAt);
    }

    public function testTheIdentifierOfAnErratumIsOne(): void
    {
        $this->expectExceptionMessageIs('The identifier of an erratum is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        new Erratum('', 'p. 1', 'Text', new DateTimeImmutable());
    }

    public function testTheLocationOfAnErratumIsOneLine(): void
    {
        $this->expectExceptionMessageIs('The location of an erratum holds a control character.');
        new Erratum('e-1', "p. 1\np. 2", 'Text', new DateTimeImmutable());
    }

    public function testTheLocationOfAnErratumIsShort(): void
    {
        $this->expectExceptionMessageIs('The location of an erratum is 1 to 120 characters.');
        new Erratum('e-1', str_repeat('p', 121), 'Text', new DateTimeImmutable());
    }

    public function testTheTextOfAnErratumIsBounded(): void
    {
        self::assertSame(4_000, mb_strlen(new Erratum('e-1', 'p', str_repeat('é', 4_000), new DateTimeImmutable())->text));
        $this->expectExceptionMessageIs('The text of an erratum is 1 to 4000 characters.');
        new Erratum('e-1', 'p', str_repeat('é', 4_001), new DateTimeImmutable());
    }

    public function testALinkKeepsItsEntriesAndItsOrdinal(): void
    {
        $link = new Link('e-2', LinkType::Expands, 'e-1', 3);

        self::assertSame('e-2', $link->from);
        self::assertSame('e-1', $link->to);
        self::assertSame(LinkType::Expands, $link->type);
        self::assertSame(3, $link->ordinal);
        self::assertSame('e-2 expands e-1', $link->key());
        self::assertSame('e-1', $link->other('e-2'));
        self::assertSame('e-2', $link->other('e-1'));
        self::assertSame(0, new Link('a', LinkType::Expands, 'b', 0)->ordinal);
        self::assertSame(Link::MAX_ORDINAL, new Link('a', LinkType::Expands, 'b', Link::MAX_ORDINAL)->ordinal);
    }

    public function testASymmetricLinkHasTheSameKeyFromBothEntries(): void
    {
        self::assertSame('a integrates_with b', new Link('a', LinkType::IntegratesWith, 'b')->key());
        self::assertSame('a integrates_with b', new Link('b', LinkType::IntegratesWith, 'a')->key());
        self::assertSame('b expands a', new Link('b', LinkType::Expands, 'a')->key());
    }

    public function testOnlyIntegratingIsSymmetric(): void
    {
        foreach (LinkType::cases() as $type) {
            self::assertSame(LinkType::IntegratesWith === $type, $type->isSymmetric());
        }
    }

    public function testAnEntryIsNotLinkedToItself(): void
    {
        $this->expectExceptionMessageIs('An entry is not linked to itself.');
        new Link('a', LinkType::EditionOf, 'a');
    }

    public function testTheEntriesOfALinkAreIdentifiers(): void
    {
        $this->expectExceptionMessageIs('The identifier of an entry is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        new Link('a', LinkType::EditionOf, '2');
    }

    public function testTheSourceOfALinkIsAnIdentifier(): void
    {
        $this->expectException(InvalidEntry::class);
        new Link('2', LinkType::EditionOf, 'a');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badOrdinals(): iterable
    {
        yield 'negative' => [-1];
        yield 'too high' => [Link::MAX_ORDINAL + 1];
    }

    #[DataProvider('badOrdinals')]
    public function testTheOrdinalOfALinkIsBounded(int $ordinal): void
    {
        $this->expectExceptionMessageIs('The ordinal of a link is from 0 to 10000.');
        new Link('a', LinkType::Expands, 'b', $ordinal);
    }

    public function testTheLevelsOfLanguageDependenceGoFromOneToFive(): void
    {
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (LanguageDependence $level): int => $level->level(), LanguageDependence::cases()));
    }

    public function testAnExperienceCountsGamesPlayedAndRun(): void
    {
        $at = new DateTimeImmutable('2027-01-10 12:00 UTC');
        $experience = new Experience(0, 0, $at);

        self::assertSame(0, $experience->played);
        self::assertSame(0, $experience->ran);
        self::assertSame($at, $experience->at);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badExperiences(): iterable
    {
        yield 'played' => [-1, 0];
        yield 'run' => [0, -1];
    }

    #[DataProvider('badExperiences')]
    public function testAnExperienceIsNeverNegative(int $played, int $ran): void
    {
        $this->expectExceptionMessageIs('The games played and run are 0 or more.');
        new Experience($played, $ran, new DateTimeImmutable());
    }
}
