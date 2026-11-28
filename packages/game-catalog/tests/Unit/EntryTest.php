<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\Erratum;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Origin;
use Tilttek\GameCatalog\Range;
use Tilttek\GameCatalog\SourcedEntry;

#[CoversClass(Entry::class)]
#[CoversClass(SourcedEntry::class)]
final class EntryTest extends TestCase
{
    public function testANewEntryHasItsSourceDetailsAndNothingElse(): void
    {
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual());

        self::assertSame('e-1', $entry->id);
        self::assertSame(DetailsTest::full()->name, $entry->details()->name);
        self::assertSame($entry->source, $entry->details());
        self::assertSame([], $entry->identifiers);
        self::assertNull($entry->corrections);
        self::assertSame([], $entry->corrected);
        self::assertSame([], $entry->errata);
        self::assertFalse($entry->hidden);
        self::assertSame(0, $entry->version);
        self::assertNull($entry->identifier(IdentifierScheme::Wikidata));
    }

    public function testTheIdentifierOfAnEntryIsOne(): void
    {
        $this->expectExceptionMessageIs('The identifier of an entry is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        new Entry('', DetailsTest::full(), Origin::manual());
    }

    public function testCorrectedValuesGoWithCorrectedFields(): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('An entry has corrected values exactly when it has corrected fields.');
        new Entry('e-1', DetailsTest::full(), Origin::manual(), corrections: DetailsTest::other());
    }

    public function testCorrectedFieldsGoWithCorrectedValues(): void
    {
        $this->expectExceptionMessageIs('An entry has corrected values exactly when it has corrected fields.');
        new Entry('e-1', DetailsTest::full(), Origin::manual(), corrected: [Field::Name]);
    }

    public function testAnEntryHasOneIdentifierOfEachDatabase(): void
    {
        $this->expectExceptionMessageIs('An entry has at most one identifier of each database.');
        new Entry('e-1', DetailsTest::full(), Origin::manual(), [ExternalId::boardGameGeek(1), ExternalId::boardGameGeek(2)]);
    }

    public function testAnEntryKeepsAtMostFiveHundredErrata(): void
    {
        $errata = array_map(static fn (int $i): Erratum => new Erratum('e'.$i, 'p', 't', new DateTimeImmutable()), range(1, Entry::MAX_ERRATA));
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual(), errata: $errata);
        self::assertCount(500, $entry->errata);

        try {
            $entry->withErratum(new Erratum('e501', 'p', 't', new DateTimeImmutable()));
            self::fail('A 501st erratum was kept.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The entry e-1 keeps at most 500 errata.', $catalogRefused->getMessage());
        }

        $this->expectExceptionMessageIs('An entry keeps at most 500 errata.');
        new Entry('e-1', DetailsTest::full(), Origin::manual(), errata: [...$errata, new Erratum('e501', 'p', 't', new DateTimeImmutable())]);
    }

    public function testACorrectionReplacesItsFieldsAndKeepsTheSource(): void
    {
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual());
        $corrected = $entry->withCorrection(DetailsTest::other(), [Field::Year, Field::Name]);

        self::assertSame([Field::Name, Field::Year], $corrected->corrected);
        self::assertSame(DetailsTest::other()->name, $corrected->details()->name);
        self::assertSame(2027, $corrected->details()->year);
        self::assertSame([3, 5], $corrected->details()->value(Field::Players));
        self::assertSame($entry->source, $corrected->source);

        $again = $corrected->withCorrection(new Details('Third', players: new Range(1, 2)), [Field::Players]);
        self::assertSame([Field::Name, Field::Year, Field::Players], $again->corrected);
        self::assertSame(DetailsTest::other()->name, $again->details()->name);
        self::assertSame(1, $again->details()->players?->min);

        $resourced = $again->withSource(new Details('From the source', year: 1999, players: new Range(4, 4), minimumAge: 8));
        self::assertSame(DetailsTest::other()->name, $resourced->details()->name);
        self::assertSame(2027, $resourced->details()->year);
        self::assertSame(8, $resourced->details()->minimumAge);
        self::assertSame('From the source', $resourced->source->name);
        self::assertSame($again->corrected, $resourced->corrected);
    }

    public function testAFieldGoesBackToItsSource(): void
    {
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual())->withCorrection(DetailsTest::other(), [Field::Name, Field::Year]);

        $year = $entry->withoutCorrection(Field::Year);
        self::assertSame([Field::Name], $year->corrected);
        self::assertSame(2026, $year->details()->year);
        self::assertSame(DetailsTest::other()->name, $year->details()->name);
        self::assertSame($entry->corrections, $year->corrections);

        $both = $year->withoutCorrection(Field::Name);
        self::assertSame([], $both->corrected);
        self::assertNull($both->corrections);
        self::assertSame(DetailsTest::full()->name, $both->details()->name);

        self::assertSame([Field::Name], $year->withoutCorrection(Field::Players)->corrected);
        self::assertSame([Field::Year], $entry->withoutCorrection(Field::Name)->corrected);
    }

    public function testAnIdentifierIsAddedOnceByDatabase(): void
    {
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual(), [ExternalId::rpgGeek(5)]);
        $with = $entry->withIdentifier(ExternalId::wikidata('Q5'));

        self::assertSame(['rpggeek:5', 'wikidata:Q5'], array_map(static fn (ExternalId $id): string => $id->key(), $with->identifiers));
        self::assertSame('Q5', $with->identifier(IdentifierScheme::Wikidata)?->value);
        self::assertSame($with, $with->withIdentifier(ExternalId::wikidata('Q5')));
        self::assertSame(
            ['rpggeek:5', 'wikidata:Q5', 'boardgamegeek:7'],
            array_map(static fn (ExternalId $id): string => $id->key(), $with->withIdentifier(ExternalId::boardGameGeek(7))->identifiers),
        );

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The entry e-1 already has the identifier rpggeek:5.');
        $with->withIdentifier(ExternalId::rpgGeek(6));
    }

    public function testErrataAreKeptInTheOrderTheyArePublished(): void
    {
        $first = new Erratum('first', 'p. 1', 'One', new DateTimeImmutable('2027-01-01'));
        $second = new Erratum('second', 'p. 2', 'Two', new DateTimeImmutable('2026-01-01'));
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual())->withErratum($first)->withErratum($second);

        self::assertSame([$first, $second], $entry->errata);
        self::assertSame($second, $entry->erratum('second'));
        self::assertNull($entry->erratum('third'));

        $this->expectExceptionMessageIs('The entry e-1 already has the erratum first.');
        $entry->withErratum(new Erratum('first', 'p. 3', 'Three', new DateTimeImmutable()));
    }

    public function testEveryCopyKeepsTheRest(): void
    {
        $erratum = new Erratum('e', 'p', 't', new DateTimeImmutable());
        $entry = new Entry('e-1', DetailsTest::full(), Origin::source('books', 'b1'), [ExternalId::rpgGeek(5)], errata: [$erratum])
            ->withCorrection(DetailsTest::other(), [Field::Name]);

        foreach ([$entry->withHidden(true), $entry->withVersion(4), $entry->withSource(DetailsTest::full()), $entry->withIdentifier(ExternalId::wikidata('Q1')), $entry->withErratum(new Erratum('f', 'p', 't', new DateTimeImmutable()))] as $copy) {
            self::assertSame('e-1', $copy->id);
            self::assertSame('books/b1', $copy->origin->key());
            self::assertSame('rpggeek:5', $copy->identifiers[0]->key());
            self::assertSame($entry->corrections, $copy->corrections);
            self::assertSame([Field::Name], $copy->corrected);
            self::assertSame($erratum, $copy->errata[0]);
        }

        self::assertTrue($entry->withHidden(true)->hidden);
        self::assertFalse($entry->withHidden(true)->withVersion(2)->withHidden(false)->hidden);
        self::assertTrue($entry->withHidden(true)->withVersion(2)->hidden);
        self::assertSame(4, $entry->withVersion(4)->version);
        self::assertSame(4, $entry->withVersion(4)->withHidden(true)->version);
    }

    public function testASourcedEntryKeepsItsOrigin(): void
    {
        $sourced = new SourcedEntry('books', 'b-1', 'e-1', DetailsTest::full(), [ExternalId::rpgGeek(5)]);

        self::assertSame('books/b-1', $sourced->origin->key());
        self::assertSame('e-1', $sourced->id);
        self::assertSame('books', $sourced->source);
        self::assertSame([], $sourced->errata);

        $this->expectExceptionMessageIs('The identifier of an entry is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        new SourcedEntry('books', 'b-1', '1', DetailsTest::full());
    }
}
