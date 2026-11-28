<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\EntryType;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\LanguageDependence;
use Tilttek\GameCatalog\Range;

#[CoversClass(Details::class)]
final class DetailsTest extends TestCase
{
    public static function full(): Details
    {
        return new Details(
            name: 'Lanterns of the Deep',
            type: EntryType::Scenario,
            kind: GameKind::RolePlaying,
            system: 'sys-fantasy',
            publishers: ['North Press', 'Ink & Dice'],
            designers: ['A. Writer'],
            year: 2026,
            players: new Range(3, 5),
            playtime: new Range(180, 240),
            minimumAge: 14,
            languages: ['fr-CA', 'en'],
            levels: new Range(1, 4),
            languageDependence: LanguageDependence::Extensive,
            description: "A descent.\nIn three acts.",
            cover: 'media-17',
        );
    }

    public static function other(): Details
    {
        return new Details(
            name: 'Lanterns of the Deep, second edition',
            type: EntryType::Supplement,
            kind: GameKind::Larp,
            system: 'sys-other',
            publishers: ['North Press'],
            designers: ['A. Writer', 'B. Writer'],
            year: 2027,
            players: new Range(2, 6),
            playtime: new Range(120, 240),
            minimumAge: 12,
            languages: ['fr'],
            levels: new Range(2, 4),
            languageDependence: LanguageDependence::Moderate,
            description: 'Another descent.',
            cover: 'media-18',
        );
    }

    public function testDetailsKeepWhatTheyAreGiven(): void
    {
        $details = self::full();

        self::assertSame('Lanterns of the Deep', $details->name);
        self::assertSame(EntryType::Scenario, $details->type);
        self::assertSame(GameKind::RolePlaying, $details->kind);
        self::assertSame('sys-fantasy', $details->system);
        self::assertSame(['North Press', 'Ink & Dice'], $details->publishers);
        self::assertSame(['A. Writer'], $details->designers);
        self::assertSame(2026, $details->year);
        self::assertSame(14, $details->minimumAge);
        self::assertSame(['fr-CA', 'en'], $details->languages);
        self::assertSame(LanguageDependence::Extensive, $details->languageDependence);
        self::assertSame("A descent.\nIn three acts.", $details->description);
        self::assertSame('media-17', $details->cover);
    }

    public function testDetailsDefaultToABoardGameWithNothingElseKnown(): void
    {
        $details = new Details(' Tide ');

        self::assertSame('Tide', $details->name);
        self::assertSame(EntryType::BaseGame, $details->type);
        self::assertSame(GameKind::Board, $details->kind);
        self::assertNull($details->system);
        self::assertSame([], $details->publishers);
        self::assertSame([], $details->designers);
        self::assertNull($details->year);
        self::assertNull($details->players);
        self::assertNull($details->playtime);
        self::assertNull($details->minimumAge);
        self::assertSame([], $details->languages);
        self::assertNull($details->levels);
        self::assertNull($details->languageDependence);
        self::assertNull($details->description);
        self::assertNull($details->cover);
    }

    public function testEachFieldHasAValueThatCompares(): void
    {
        $details = self::full();

        self::assertSame([
            'name' => 'Lanterns of the Deep',
            'type' => 'scenario',
            'kind' => 'role-playing',
            'system' => 'sys-fantasy',
            'publishers' => ['North Press', 'Ink & Dice'],
            'designers' => ['A. Writer'],
            'year' => 2026,
            'players' => [3, 5],
            'playtime' => [180, 240],
            'minimum_age' => 14,
            'languages' => ['fr-CA', 'en'],
            'levels' => [1, 4],
            'language_dependence' => 'extensive',
            'description' => "A descent.\nIn three acts.",
            'cover' => 'media-17',
        ], array_combine(
            array_map(static fn (Field $field): string => $field->value, Field::cases()),
            array_map($details->value(...), Field::cases()),
        ));

        $empty = new Details('Tide');

        foreach ([Field::Players, Field::Playtime, Field::Levels, Field::LanguageDependence] as $field) {
            self::assertNull($empty->value($field));
        }
    }

    public function testTheDifferencesAreTheFieldsWhoseValueDiffers(): void
    {
        self::assertSame([], self::full()->differences(self::full()));
        self::assertSame(Field::cases(), self::full()->differences(self::other()));
        self::assertSame([Field::Players], self::full()->differences(self::full()->merge(new Details('x', players: new Range(3, 6)), [Field::Players])));
    }

    public function testMergingTakesTheGivenFieldsFromTheOther(): void
    {
        $full = self::full();
        $other = self::other();

        self::assertSame([], $full->merge($other, [])->differences($full));
        self::assertSame([], $full->merge($other, Field::cases())->differences($other));

        foreach (Field::cases() as $field) {
            self::assertSame([$field], $full->differences($full->merge($other, [$field])), $field->value);
            self::assertSame($other->value($field), $full->merge($other, [$field])->value($field));
        }
    }

    public function testTheBoundsOfEachFieldAreAccepted(): void
    {
        $details = new Details(
            name: str_repeat('n', Details::MAX_NAME),
            publishers: array_map(static fn (int $i): string => 'P'.$i, range(1, Details::MAX_PEOPLE)),
            designers: [str_repeat('d', Details::MAX_PERSON)],
            year: 1,
            players: new Range(1, Details::MAX_PLAYERS),
            playtime: new Range(1, Range::MAX),
            minimumAge: 0,
            languages: array_map(static fn (int $i): string => 'en-'.$i, range(0, Details::MAX_LANGUAGES - 1)),
            levels: new Range(0, Details::MAX_LEVEL),
            description: str_repeat('d', Details::MAX_DESCRIPTION),
        );

        self::assertCount(Details::MAX_PEOPLE, $details->publishers);
        self::assertCount(Details::MAX_LANGUAGES, $details->languages);
        self::assertSame(Details::MAX_YEAR, new Details('x', year: Details::MAX_YEAR, minimumAge: Details::MAX_AGE)->year);
        self::assertSame(['zh-Hant-TW', 'sr-Latn', 'gsw', 'en-a-bbb-x-abcdefgh'], new Details('x', languages: ['zh-Hant-TW', 'sr-Latn', 'gsw', 'en-a-bbb-x-abcdefgh'])->languages);
    }

    /**
     * @return iterable<string, array{callable(): Details, string}>
     */
    public static function badDetails(): iterable
    {
        yield 'an empty name' => [static fn (): Details => new Details(' '), 'The name of a game is 1 to 200 characters.'];
        yield 'a long name' => [static fn (): Details => new Details(str_repeat('n', 201)), 'The name of a game is 1 to 200 characters.'];
        yield 'a system that is not an identifier' => [static fn (): Details => new Details('x', system: 'the system'), 'The identifier of a game system is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'a cover that is not an identifier' => [static fn (): Details => new Details('x', cover: 'https://example.com/a.png'), 'The identifier of a cover is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'too many publishers' => [static fn (): Details => new Details('x', publishers: array_map(static fn (int $i): string => 'P'.$i, range(0, 20))), 'An entry names at most 20 of each: publishers, designers.'];
        yield 'too many designers' => [static fn (): Details => new Details('x', designers: array_map(static fn (int $i): string => 'D'.$i, range(0, 20))), 'An entry names at most 20 of each: publishers, designers.'];
        yield 'a publisher twice' => [static fn (): Details => new Details('x', publishers: ['North', ' North']), 'An entry names each publisher once.'];
        yield 'a designer twice' => [static fn (): Details => new Details('x', designers: ['A', 'A']), 'An entry names each designer once.'];
        yield 'an empty publisher' => [static fn (): Details => new Details('x', publishers: ['']), 'The name of a publisher is 1 to 120 characters.'];
        yield 'a long designer' => [static fn (): Details => new Details('x', designers: [str_repeat('d', 121)]), 'The name of a designer is 1 to 120 characters.'];
        yield 'a year 0' => [static fn (): Details => new Details('x', year: 0), 'The year of publication is from 1 to 9999.'];
        yield 'a year 10000' => [static fn (): Details => new Details('x', year: 10_000), 'The year of publication is from 1 to 9999.'];
        yield 'a negative age' => [static fn (): Details => new Details('x', minimumAge: -1), 'The minimum age is from 0 to 99.'];
        yield 'an age of 100' => [static fn (): Details => new Details('x', minimumAge: 100), 'The minimum age is from 0 to 99.'];
        yield 'no player' => [static fn (): Details => new Details('x', players: new Range(0, 4)), 'The numbers of players are from 1 to 999.'];
        yield 'a thousand players' => [static fn (): Details => new Details('x', players: new Range(1, 1_000)), 'The numbers of players are from 1 to 999.'];
        yield 'no minute' => [static fn (): Details => new Details('x', playtime: new Range(0, 30)), 'The minutes of a game are from 1 to 100000.'];
        yield 'levels over 1000' => [static fn (): Details => new Details('x', levels: new Range(1, 1_001)), 'The levels of a scenario are from 0 to 1000.'];
        yield 'too many languages' => [static fn (): Details => new Details('x', languages: array_map(static fn (int $i): string => 'en-'.$i, range(0, 50))), 'An entry names at most 50 languages.'];
        yield 'a language that is not a tag' => [static fn (): Details => new Details('x', languages: ['French']), 'A language is a BCP 47 tag, as "fr" or "fr-CA".'];
        yield 'a language with a line break' => [static fn (): Details => new Details('x', languages: ["fr\n"]), 'A language is a BCP 47 tag, as "fr" or "fr-CA".'];
        yield 'a language with too many subtags' => [static fn (): Details => new Details('x', languages: ['fr-a-b-c-d-e']), 'A language is a BCP 47 tag, as "fr" or "fr-CA".'];
        yield 'a language with a long subtag' => [static fn (): Details => new Details('x', languages: ['fr-abcdefghi']), 'A language is a BCP 47 tag, as "fr" or "fr-CA".'];
        yield 'a language twice' => [static fn (): Details => new Details('x', languages: ['fr', 'fr']), 'An entry names each language once.'];
        yield 'a description too long' => [static fn (): Details => new Details('x', description: str_repeat('d', 4_001)), 'The description of a game is 1 to 4000 characters.'];
    }

    /**
     * @param callable(): Details $details
     */
    #[DataProvider('badDetails')]
    public function testEachFieldIsWithinItsShapeAndBounds(callable $details, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs($message);
        $details();
    }
}
