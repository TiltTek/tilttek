<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Localisation;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

/**
 * The records a table plays by: rule variants, difficulty numbers and party roles.
 */
#[CoversClass(RuleVariant::class)]
#[CoversClass(PartyRole::class)]
#[CoversClass(Difficulty::class)]
#[CoversClass(DifficultyBand::class)]
#[CoversClass(GameSystem::class)]
#[CoversClass(Localisation::class)]
#[CoversClass(SystemWriter::class)]
#[CoversClass(SystemReader::class)]
#[CoversClass(UnknownRecord::class)]
final class PlayRecordsTest extends TestCase
{
    private const string EXAMPLES = __DIR__.'/../../examples';

    private Provenance $source;

    protected function setUp(): void
    {
        $this->source = new Provenance('Lanterns', Licence::Owned);
    }

    public function testAVariantAndARoleHoldTheirKeyNamesAndDescriptions(): void
    {
        $variant = new RuleVariant('long-night', Translations::of(['en' => 'The long night']), $this->source, Translations::of(['en' => 'Darker.']));
        $role = new PartyRole('bearer', Translations::of(['en' => 'Bearer']), $this->source);

        self::assertSame(['long-night', 'The long night', 'Darker.'], [$variant->key, $variant->names->in('en'), $variant->descriptions->in('en')]);
        self::assertSame(['bearer', 'Bearer', true], [$role->key, $role->names->in('en'), $role->descriptions->isEmpty()]);
        self::assertSame($this->source, $role->provenance);
    }

    /**
     * @return iterable<string, array{callable(Provenance): mixed, string}>
     */
    public static function badRecords(): iterable
    {
        yield 'a variant without name' => [static fn (Provenance $source): RuleVariant => new RuleVariant('v', new Translations(), $source), 'The rule variant "v" has no name.'];
        yield 'a variant of a bad key' => [static fn (Provenance $source): RuleVariant => new RuleVariant('V', Translations::of(['en' => 'V']), $source), 'The key of a rule variant "V" must be'];
        yield 'a role without name' => [static fn (Provenance $source): PartyRole => new PartyRole('r', new Translations(), $source), 'The party role "r" has no name.'];
        yield 'a role of a bad key' => [static fn (Provenance $source): PartyRole => new PartyRole('r r', Translations::of(['en' => 'R']), $source), 'The key of a party role "r r" must be'];
        yield 'a band without name' => [static fn (): DifficultyBand => new DifficultyBand('easy', new Translations()), 'The band of difficulty "easy" has no name.'];
        yield 'a band of a bad key' => [static fn (): DifficultyBand => new DifficultyBand('Easy', Translations::of(['en' => 'Easy'])), 'The key of a band of difficulty "Easy" must be'];
        yield 'a band number too high' => [static fn (): DifficultyBand => new DifficultyBand('easy', Translations::of(['en' => 'Easy']), 1_000_000_001), 'The number of the band of difficulty "easy" lies within ±1000000000.'];
        yield 'a band number too low' => [static fn (): DifficultyBand => new DifficultyBand('easy', Translations::of(['en' => 'Easy']), -1_000_000_001), 'lies within ±1000000000.'];
        yield 'numbers without name' => [static fn (Provenance $source): Difficulty => new Difficulty('check', new Translations(), $source), 'The difficulty numbers "check" have no name.'];
        yield 'numbers of a bad key' => [static fn (Provenance $source): Difficulty => new Difficulty('Check', Translations::of(['en' => 'C']), $source), 'The key of difficulty numbers "Check" must be'];
        yield 'a bad method' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, method: 'By Budget'), 'The key of the method of the difficulty numbers "check" "By Budget"'];
        yield 'a band twice' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, bands: [self::band('easy'), self::band('easy')]), 'The difficulty numbers "check" hold the band "easy" twice.'];
        yield 'too many bands' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, bands: array_map(static fn (int $i): DifficultyBand => self::band('b'.$i), range(1, 25))), 'The difficulty numbers "check" hold at most 24 bands.'];
        yield 'a bad label' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['Costs' => 1]), 'A label of the difficulty numbers "check" is 1 to 32 lowercase letters, digits, "+", "/", ".", "_" or "-"; "Costs" is not.'];
        yield 'a long label' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: [str_repeat('a', 33) => 1]), 'is not.'];
        yield 'a text number' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['costs' => ['1' => '200']]), 'The difficulty numbers "check" hold whole numbers within ±1000000000, in tables 3 levels deep at most; "1" is not one.'];
        yield 'a number too high' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['costs' => 1_000_000_001]), '"costs" is not one.'];
        yield 'a number too low' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['costs' => -1_000_000_001]), '"costs" is not one.'];
        yield 'a table too deep' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['a' => ['b' => ['c' => ['d' => 1]]]]), '"c" is not one.'];
        yield 'too many numbers' => [static fn (Provenance $source): Difficulty => new Difficulty('check', Translations::of(['en' => 'C']), $source, numbers: ['a' => array_fill(0, 10_001, 1)]), 'The difficulty numbers "check" hold at most 10000 numbers.'];
    }

    /**
     * @param callable(Provenance): mixed $make
     */
    #[DataProvider('badRecords')]
    public function testABadRecordIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make($this->source);
    }

    public function testDifficultyNumbersKeepTheirBandsAndTables(): void
    {
        $numbers = new Difficulty('encounter', Translations::of(['en' => 'Encounter']), $this->source, Translations::of(['en' => 'By budget.']), 'budget', [
            self::band('low'),
            self::band('high', 5),
            self::band('deadly', 0),
        ], [
            'budgets' => ['1' => ['low' => 50, 'high' => 100]],
            'costs' => ['1/8' => 25, '-4' => 0],
            'party-size' => 4,
            'empty' => [],
        ]);

        self::assertSame(['low', 'high', 'deadly'], $numbers->bandKeys());
        self::assertSame('budget', $numbers->method);
        self::assertSame('By budget.', $numbers->descriptions->in('en'));
        self::assertTrue($numbers->hasBand('high'));
        self::assertFalse($numbers->hasBand('trivial'));
        self::assertSame(5, $numbers->band('high')?->number);
        self::assertSame(0, $numbers->band('deadly')?->number);
        self::assertNull($numbers->band('low')?->number);
        self::assertNull($numbers->band('trivial'));
        self::assertSame(100, $numbers->number('budgets', 1, 'high'));
        self::assertSame(100, $numbers->number('budgets', '1', 'high'));
        self::assertSame(['low' => 50, 'high' => 100], $numbers->number('budgets', '1'));
        self::assertSame(25, $numbers->number('costs', '1/8'));
        self::assertSame(0, $numbers->number('costs', -4));
        self::assertSame(4, $numbers->number('party-size'));
        self::assertNull($numbers->number('party-size', 'x'));
        self::assertNull($numbers->number('budgets', '2'));
        self::assertSame([], $numbers->number('empty'));
        self::assertSame($numbers->numbers, $numbers->number());

        // The bounds hold: 24 bands, 10 000 numbers, a number of ±1 000 000 000, three levels.
        $wide = new Difficulty('wide', Translations::of(['en' => 'Wide']), $this->source, bands: array_map(static fn (int $i): DifficultyBand => self::band('b'.$i), range(1, 24)), numbers: [
            'a' => array_fill(0, 9_998, 1),
            'b' => ['c' => ['d' => 1_000_000_000]],
            str_repeat('z', 32) => -1_000_000_000,
        ]);
        self::assertCount(24, $wide->bands);
        self::assertSame(-1_000_000_000, $wide->number(str_repeat('z', 32)));
        self::assertSame(1_000_000_000, new DifficultyBand('top', Translations::of(['en' => 'Top']), 1_000_000_000)->number);
        self::assertSame(-1_000_000_000, new DifficultyBand('bottom', Translations::of(['en' => 'Bottom']), -1_000_000_000)->number);
    }

    public function testASystemLooksItsPlayRecordsUpByKeyInOrder(): void
    {
        $system = $this->lanterns();

        self::assertSame(['long-night'], array_map(static fn (RuleVariant $variant): string => $variant->key, $system->variants()));
        self::assertSame(['check', 'encounter'], array_map(static fn (Difficulty $numbers): string => $numbers->key, $system->difficulties()));
        self::assertSame(['bearer', 'watcher'], array_map(static fn (PartyRole $role): string => $role->key, $system->partyRoles()));
        self::assertTrue($system->hasVariant('long-night'));
        self::assertFalse($system->hasVariant('short-day'));
        self::assertTrue($system->hasDifficulty('encounter'));
        self::assertFalse($system->hasDifficulty('chase'));
        self::assertTrue($system->hasPartyRole('watcher'));
        self::assertFalse($system->hasPartyRole('healer'));
        self::assertSame('The long night', $system->variant('long-night')->names->in('en'));
        self::assertSame(9, $system->difficulty('check')->band('hard')?->number);
        self::assertSame('Porte-lanterne', $system->partyRole('bearer')->names->in('fr'));
        self::assertSame(4, $system->difficulty('encounter')->number('budgets', 1, 'tense'));
    }

    /**
     * @return iterable<string, array{callable(GameSystem): mixed, string}>
     */
    public static function unknownRecords(): iterable
    {
        yield 'a variant' => [static fn (GameSystem $system): RuleVariant => $system->variant('short-day'), 'The system "lanterns" has no rule variant with the key "short-day".'];
        yield 'difficulty numbers' => [static fn (GameSystem $system): Difficulty => $system->difficulty('chase'), 'The system "lanterns" has no difficulty numbers with the key "chase".'];
        yield 'a role' => [static fn (GameSystem $system): PartyRole => $system->partyRole('healer'), 'The system "lanterns" has no party role with the key "healer".'];
    }

    /**
     * @param callable(GameSystem): mixed $find
     */
    #[DataProvider('unknownRecords')]
    public function testAnUnknownPlayRecordIsRefused(callable $find, string $message): void
    {
        $this->expectException(UnknownRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        $find($this->lanterns());
    }

    /**
     * @return iterable<string, array{callable(Translations, Provenance): GameSystem, string}>
     */
    public static function twins(): iterable
    {
        yield 'variants' => [static fn (Translations $names, Provenance $source): GameSystem => new GameSystem('twins', $names, $source, variants: [new RuleVariant('x', $names, $source), new RuleVariant('x', $names, $source)]), 'The system "twins" holds two of the rule variant "x".'];
        yield 'difficulties' => [static fn (Translations $names, Provenance $source): GameSystem => new GameSystem('twins', $names, $source, difficulties: [new Difficulty('x', $names, $source), new Difficulty('x', $names, $source)]), 'The system "twins" holds two of the difficulty numbers "x".'];
        yield 'roles' => [static fn (Translations $names, Provenance $source): GameSystem => new GameSystem('twins', $names, $source, partyRoles: [new PartyRole('x', $names, $source), new PartyRole('x', $names, $source)]), 'The system "twins" holds two of the party role "x".'];
    }

    /**
     * @param callable(Translations, Provenance): GameSystem $make
     */
    #[DataProvider('twins')]
    public function testASystemRefusesTwoPlayRecordsOfOneKey(callable $make, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make(Translations::of(['en' => 'X']), $this->source);
    }

    public function testThePlayRecordsCountTowardsTheMostRecords(): void
    {
        $names = Translations::of(['en' => 'X']);
        $terms = array_map(fn (int $i): Term => new Term('t'.$i, $names, $this->source), range(1, 9_997));
        $variant = new RuleVariant('v', $names, $this->source);
        $numbers = new Difficulty('d', $names, $this->source);
        $role = new PartyRole('r', $names, $this->source);

        self::assertCount(1, new GameSystem('full', $names, $this->source, null, [], [], $terms, [], [$variant], [$numbers], [$role])->partyRoles());

        $makes = [
            fn (): GameSystem => new GameSystem('full', $names, $this->source, null, [], [], $terms, [], [$variant, new RuleVariant('v2', $names, $this->source)], [$numbers], [$role]),
            fn (): GameSystem => new GameSystem('full', $names, $this->source, null, [], [], $terms, [], [$variant], [$numbers, new Difficulty('d2', $names, $this->source)], [$role]),
            fn (): GameSystem => new GameSystem('full', $names, $this->source, null, [], [], $terms, [], [$variant], [$numbers], [$role, new PartyRole('r2', $names, $this->source)]),
        ];

        foreach ($makes as $make) {
            try {
                $make();
                self::fail('The system was kept.');
            } catch (InvalidRecord $invalidRecord) {
                self::assertSame('The system "full" holds more than 10000 records.', $invalidRecord->getMessage());
            }
        }
    }

    public function testThePlayRecordsAreSourcesOfTheSystem(): void
    {
        $other = new Provenance('Open roles', Licence::CcBy40, 'Roles, CC BY 4.0.');
        $variantSource = new Provenance('Open variants', Licence::CcBy40, 'Variants, CC BY 4.0.');
        $numbersSource = new Provenance('Open numbers', Licence::Cc0);
        $names = Translations::of(['en' => 'X']);
        $system = new GameSystem('mixed', $names, $this->source, variants: [new RuleVariant('v', $names, $variantSource)], difficulties: [new Difficulty('d', $names, $numbersSource)], partyRoles: [new PartyRole('r', $names, $other)]);

        self::assertSame([$this->source, $variantSource, $numbersSource, $other], $system->provenances());
    }

    public function testALocalisationLaysItsTextsOverThePlayRecords(): void
    {
        $system = $this->lanterns();
        $german = $system->localised(new Localisation(
            'de',
            new Provenance('Laternen', Licence::CcBy40, 'German, CC BY 4.0.'),
            variants: ['long-night' => ['name' => 'Die lange Nacht', 'description' => 'Dunkler.']],
            difficulties: ['check' => ['name' => 'Schwierigkeit', 'description' => 'Einer Handlung.', 'bands' => ['hard' => 'Schwer']], 'encounter' => ['bands' => ['calm' => 'Ruhig']]],
            partyRoles: ['watcher' => ['name' => 'Wächter', 'description' => 'Schaut.']],
        ));

        self::assertSame(['Die lange Nacht', 'Dunkler.'], [$german->variant('long-night')->names->in('de'), $german->variant('long-night')->descriptions->in('de')]);
        self::assertSame("A lantern's glow falls by one more at each scene.", $german->variant('long-night')->descriptions->in('en'));
        $check = $german->difficulty('check');
        self::assertSame(['Schwierigkeit', 'Einer Handlung.', 'Schwer', 'Easy'], [$check->names->in('de'), $check->descriptions->in('de'), $check->band('hard')?->names->in('de'), $check->band('easy')?->names->in('de')]);
        self::assertSame(['check', 9, ['easy', 'hard', 'desperate']], [$check->method, $check->band('hard')?->number, $check->bandKeys()]);
        self::assertSame($system->difficulty('encounter')->numbers, $german->difficulty('encounter')->numbers);
        self::assertSame(['Ruhig', 'Threat of a scene'], [$german->difficulty('encounter')->band('calm')?->names->in('de'), $german->difficulty('encounter')->names->in('de')]);
        self::assertSame(['Wächter', 'Schaut.', 'Lantern bearer'], [$german->partyRole('watcher')->names->in('de'), $german->partyRole('watcher')->descriptions->in('de'), $german->partyRole('bearer')->names->in('de')]);
        self::assertSame($system->partyRole('bearer')->provenance, $german->partyRole('bearer')->provenance);
    }

    /**
     * @return iterable<string, array{callable(Provenance): Localisation, string}>
     */
    public static function badLocalisations(): iterable
    {
        yield 'an unknown variant' => [static fn (Provenance $source): Localisation => new Localisation('de', $source, variants: ['short-day' => ['name' => 'X']]), 'The system "lanterns" has no rule variant with the key "short-day".'];
        yield 'unknown numbers' => [static fn (Provenance $source): Localisation => new Localisation('de', $source, difficulties: ['chase' => ['name' => 'X']]), 'The system "lanterns" has no difficulty numbers with the key "chase".'];
        yield 'an unknown band' => [static fn (Provenance $source): Localisation => new Localisation('de', $source, difficulties: ['check' => ['bands' => ['hard' => 'Schwer', 'heroic' => 'X']]]), 'The system "lanterns" has no band of difficulty with the key "heroic".'];
        yield 'an unknown role' => [static fn (Provenance $source): Localisation => new Localisation('de', $source, partyRoles: ['healer' => ['name' => 'X']]), 'The system "lanterns" has no party role with the key "healer".'];
    }

    /**
     * @param callable(Provenance): Localisation $localisation
     */
    #[DataProvider('badLocalisations')]
    public function testALocalisationOfAnUnknownPlayRecordIsRefused(callable $localisation, string $message): void
    {
        $this->expectException(UnknownRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->lanterns()->localised($localisation($this->source));
    }

    public function testTheReaderReadsThePlayRecordsAndTheirLocalisation(): void
    {
        $system = new SystemReader()->load(self::EXAMPLES.'/lanterns.yaml', self::EXAMPLES.'/lanterns.es.yaml')->get('lanterns');

        self::assertSame('La larga noche', $system->variant('long-night')->names->in('es'));
        self::assertSame(['Dificultad de una acción', 'Fácil', 'Difícil', 'Desperate'], [
            $system->difficulty('check')->names->in('es'),
            $system->difficulty('check')->band('easy')?->names->in('es'),
            $system->difficulty('check')->band('hard')?->names->in('es'),
            $system->difficulty('check')->band('desperate')?->names->in('es'),
        ]);
        self::assertSame(['budgets' => [1 => ['calm' => 2, 'tense' => 4], 2 => ['calm' => 3, 'tense' => 6]], 'costs' => ['1/2' => 1, 1 => 2, 2 => 4]], $system->difficulty('encounter')->numbers);
        self::assertSame('Portador de la linterna', $system->partyRole('bearer')->names->in('es'));
        self::assertSame('Watcher', $system->partyRole('watcher')->names->in('es'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badDocuments(): iterable
    {
        yield 'an unknown field of a variant' => [['variants' => [['key' => 'v', 'names' => 'V', 'rank' => 1]]], 'variants[0]: "rank" is not one of key, names, descriptions, source.'];
        yield 'an unknown field of a role' => [['roles' => [['key' => 'r', 'names' => 'R', 'minimum' => 1]]], 'roles[0]: "minimum" is not one of key, names, descriptions, source.'];
        yield 'an unknown field of numbers' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'table' => []]]], 'difficulties[0]: "table" is not one of key, names, descriptions, method, bands, numbers, source.'];
        yield 'an unknown field of a band' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'bands' => [['key' => 'b', 'names' => 'B', 'dc' => 5]]]]], 'difficulties[0].bands[0]: "dc" is not one of key, names, number.'];
        yield 'a band number as text' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'bands' => [['key' => 'b', 'names' => 'B', 'number' => '5']]]]], 'difficulties[0].bands[0].number: a whole number is expected.'];
        yield 'a band without name' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'bands' => [['key' => 'b']]]]], 'difficulties[0].bands[0]: The band of difficulty "b" has no name.'];
        yield 'too many bands' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'bands' => array_fill(0, 25, ['key' => 'b', 'names' => 'B'])]]], 'difficulties[0].bands: more than 24 items.'];
        yield 'numbers as text' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'numbers' => 'many']]], 'difficulties[0].numbers: a mapping of labels to numbers or tables is expected.'];
        yield 'a bad number' => [['difficulties' => [['key' => 'd', 'names' => 'D', 'numbers' => ['costs' => 1.5]]]], 'difficulties[0]: The difficulty numbers "d" hold whole numbers'];
        yield 'an unknown field of a localised record' => [['kind' => 'localisation', 'variants' => ['long-night' => ['names' => 'X']]], 'variants.long-night: "names" is not one of name, description.'];
        yield 'an unknown field of localised numbers' => [['kind' => 'localisation', 'difficulties' => ['check' => ['method' => 'x']]], 'difficulties.check: "method" is not one of name, description, bands.'];
        yield 'a band name as a list' => [['kind' => 'localisation', 'difficulties' => ['check' => ['bands' => ['easy' => ['x']]]]], 'difficulties.check.bands.easy: a text is expected.'];
        yield 'an unknown field of a localised role' => [['kind' => 'localisation', 'roles' => ['bearer' => ['minimum' => 1]]], 'roles.bearer: "minimum" is not one of name, description.'];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('badDocuments')]
    public function testABrokenPlayRecordIsRefusedWithItsPlace(array $fields, string $message): void
    {
        $reader = new SystemReader();
        $source = ['title' => 'T', 'licence' => 'owned'];

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        if ('localisation' === ($fields['kind'] ?? null)) {
            $reader->localise($this->lanterns(), ['format' => SystemReader::FORMAT, 'system' => 'lanterns', 'locale' => 'de', 'source' => $source] + $fields);

            return;
        }

        $reader->system(['format' => SystemReader::FORMAT, 'system' => ['key' => 'x', 'names' => 'X'], 'source' => $source] + $fields);
    }

    public function testALocalisationDocumentWithoutPlayRecordsLeavesThemAsTheyAre(): void
    {
        $system = $this->lanterns();
        $localised = new SystemReader()->localise($system, ['format' => SystemReader::FORMAT, 'kind' => 'localisation', 'system' => 'lanterns', 'locale' => 'de', 'source' => ['title' => 'T', 'licence' => 'owned'], 'difficulties' => ['check' => ['name' => 'Probe']]]);

        self::assertSame('Probe', $localised->difficulty('check')->names->in('de'));
        self::assertSame(['Easy', 'Lantern bearer'], [$localised->difficulty('check')->band('easy')?->names->in('de'), $localised->partyRole('bearer')->names->in('de')]);
    }

    public function testTheReaderKeepsOnlyTheTextsAGivenLocalisationStates(): void
    {
        $reader = new SystemReader();
        $header = ['format' => SystemReader::FORMAT, 'kind' => 'localisation', 'system' => 'lanterns', 'locale' => 'de', 'source' => ['title' => 'T', 'licence' => 'owned']];

        self::assertSame(['check' => ['name' => 'Probe']], $reader->localisation($header + ['difficulties' => ['check' => ['name' => 'Probe']]])->difficulties);
        self::assertSame(['check' => ['description' => 'Eine Handlung.']], $reader->localisation($header + ['difficulties' => ['check' => ['description' => 'Eine Handlung.']]])->difficulties);
        self::assertSame(['check' => ['bands' => ['easy' => 'Leicht']]], $reader->localisation($header + ['difficulties' => ['check' => ['bands' => ['easy' => 'Leicht']]]])->difficulties);
        self::assertSame(
            ['check' => ['name' => 'Probe', 'description' => 'Eine Handlung.', 'bands' => ['easy' => 'Leicht', 'hard' => 'Schwer']]],
            $reader->localisation($header + ['difficulties' => ['check' => ['name' => 'Probe', 'description' => 'Eine Handlung.', 'bands' => ['easy' => 'Leicht', 'hard' => 'Schwer']]]])->difficulties,
        );
        self::assertSame([], $reader->localisation($header + ['difficulties' => ['check' => []]])->difficulties['check']);
    }

    public function testTheWriterLeavesOutTheNumberOfABandThatHasNone(): void
    {
        $source = new Provenance('Own', Licence::Owned);
        $system = new GameSystem('own', Translations::of(['en' => 'Own']), $source, difficulties: [
            new Difficulty('threat', Translations::of(['en' => 'Threat']), $source, bands: [self::band('low'), self::band('high', 3), self::band('zero', 0)]),
        ]);
        $document = new SystemWriter()->toArray($system);
        self::assertIsArray($document['difficulties']);
        self::assertIsArray($document['difficulties'][0]);
        $bands = $document['difficulties'][0]['bands'];

        self::assertSame([
            ['key' => 'low', 'names' => ['en' => 'Low']],
            ['key' => 'high', 'names' => ['en' => 'High'], 'number' => 3],
            ['key' => 'zero', 'names' => ['en' => 'Zero'], 'number' => 0],
        ], $bands);
    }

    public function testNumbersAreCheckedAfterANestedTable(): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('"b" is not one');

        new Difficulty('numbers', Translations::of(['en' => 'Numbers']), $this->source, numbers: ['a' => ['x' => 1], 'b' => 'bad']);
    }

    private static function band(string $key, ?int $number = null): DifficultyBand
    {
        return new DifficultyBand($key, Translations::of(['en' => ucfirst($key)]), $number);
    }

    private function lanterns(): GameSystem
    {
        return new SystemReader()->load(self::EXAMPLES.'/lanterns.yaml')->get('lanterns');
    }
}
