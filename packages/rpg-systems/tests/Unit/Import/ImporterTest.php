<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\Import\Importer;
use Tilttek\RpgSystems\Import\ImportProfile;
use Tilttek\RpgSystems\Import\TableMapping;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Term;

#[CoversClass(Importer::class)]
#[CoversClass(ImportProfile::class)]
#[CoversClass(TableMapping::class)]
final class ImporterTest extends TestCase
{
    private const string EXAMPLES = __DIR__.'/../../../examples';

    private Provenance $srd;

    private Provenance $orc;

    protected function setUp(): void
    {
        $this->srd = new Provenance('System Reference Document 5.2', Licence::CcBy40, 'Material from the SRD 5.2, under CC BY 4.0.', 'A publisher', 'https://example.org/srd');
        $this->orc = new Provenance('Pathfinder rules', Licence::Orc, 'Material used under the ORC License.');
    }

    public function testAnSrdExtractBecomesASystemWhoseEveryRecordCarriesItsSource(): void
    {
        $system = new Importer()->importText(ImportProfile::srd52(), (string) file_get_contents(self::EXAMPLES.'/srd-5.2-extract.json'), Syntax::Json, $this->srd);

        self::assertSame('srd-5.2', $system->key);
        self::assertSame('System Reference Document 5.2', $system->name());
        self::assertSame('5.2', $system->version);
        self::assertSame($this->srd, $system->provenance);
        self::assertSame(['str', 'dex', 'con', 'int', 'wis', 'cha'], array_map(static fn (Attribute $attribute): string => $attribute->key, $system->attributes()));
        $strength = $system->attribute('str');
        self::assertSame(['en' => 'Strength'], $strength->names->texts);
        self::assertSame(['en' => 'STR'], $strength->abbreviations->texts);
        self::assertSame(['en' => 'Physical might.'], $strength->descriptions->texts);
        self::assertSame([1, 30], [$strength->minimum, $strength->maximum]);
        self::assertTrue($system->attribute('con')->descriptions->isEmpty());
        self::assertSame(
            ['skill.acrobatics', 'skill.sleight-of-hand', 'condition.blinded', 'condition.prone', 'damage-type.fire'],
            array_map(static fn (Term $term): string => $term->key, $system->terms()),
        );
        self::assertSame("The creature cannot see.\n\nIts sight-based checks fail.", $system->term('condition.blinded')->descriptions->in('en'));
        self::assertSame('The creature lies on the ground.', $system->term('condition.prone')->descriptions->in('en'));
        self::assertSame('condition', $system->term('condition.prone')->category);
        self::assertSame(['skill', 'condition', 'damage-type'], $system->categories());
        self::assertSame(['1d20', '2d20kh1', '2d20kl1'], [$system->dice('d20-test')->notation, $system->dice('advantage')->notation, $system->dice('disadvantage')->notation]);
        self::assertSame('Advantage', $system->dice('advantage')->names->in('en'));
        self::assertSame([$this->srd], $system->provenances());
        self::assertFalse($system->hasTerm('monster.left-out'));
        self::assertEquals($system, new SystemReader()->system(new SystemWriter()->toArray($system)));
    }

    public function testAnSrd51ExtractBecomesTheSystemOfThe2014Rules(): void
    {
        $srd = new Provenance('System Reference Document 5.1', Licence::CcBy40, 'Material from the SRD 5.1, under CC BY 4.0.');
        $system = new Importer()->importText(ImportProfile::srd51(), (string) file_get_contents(self::EXAMPLES.'/srd-5.1-extract.json'), Syntax::Json, $srd);

        self::assertSame(['srd-5.1', 'System Reference Document 5.1', '5.1'], [$system->key, $system->name(), $system->version]);
        self::assertSame(['Strength', 'STR', 'Bodily power.', 1, 30], [$system->attribute('str')->names->in('en'), $system->attribute('str')->abbreviations->in('en'), $system->attribute('str')->descriptions->in('en'), $system->attribute('str')->minimum, $system->attribute('str')->maximum]);
        self::assertSame(['skill.athletics', 'condition.grappled', 'magic-school.evocation'], array_map(static fn (Term $term): string => $term->key, $system->terms()));
        self::assertSame('Its speed becomes 0.', $system->term('condition.grappled')->descriptions->in('en'));
        self::assertSame(['1d20', '2d20kh1', '2d20kl1'], array_map(static fn (DiceExpression $dice): string => $dice->notation, $system->diceExpressions()));
        self::assertSame(['d20-roll', 'advantage', 'disadvantage'], array_map(static fn (DiceExpression $dice): string => $dice->key, $system->diceExpressions()));
        self::assertSame(['d20 Roll', 'Advantage', 'Disadvantage'], array_map(static fn (DiceExpression $dice): ?string => $dice->names->in('en'), $system->diceExpressions()));
        self::assertSame([Licence::CcBy40], ImportProfile::srd51()->licences);
    }

    public function testAFateCoreExtractBecomesASystemUnderCcBy30(): void
    {
        $fate = new Provenance('Fate Core System', Licence::CcBy30, 'This work is based on Fate Core System, under CC BY 3.0.');
        $system = new Importer()->importText(ImportProfile::fateCore(), (string) file_get_contents(self::EXAMPLES.'/fate-core-extract.yaml'), Syntax::Yaml, $fate);

        self::assertSame(['fate-core', 'Fate Core System', null], [$system->key, $system->name(), $system->version]);
        self::assertSame([], $system->attributes());
        self::assertSame(['skill.notice', 'skill.will', 'approach.careful', 'ladder.fair', 'ladder.good'], array_map(static fn (Term $term): string => $term->key, $system->terms()));
        self::assertSame(['skill', 'approach', 'ladder'], $system->categories());
        self::assertSame('Spotting what is there to see.', $system->term('skill.notice')->descriptions->in('en'));
        self::assertSame('+3', $system->term('ladder.good')->descriptions->in('en'));
        self::assertSame(['4dF', 'Fate roll'], [$system->dice('fate-roll')->notation, $system->dice('fate-roll')->names->in('en')]);
        self::assertCount(1, $system->diceExpressions());

        $this->expectException(InvalidDocument::class);
        new Importer()->importText(ImportProfile::fateCore(), 'skills: []', Syntax::Yaml, $this->srd);
    }

    public function testAPathfinderExtractBecomesASystemUnderTheOrcLicence(): void
    {
        $system = new Importer()->importText(ImportProfile::pathfinder2e(), (string) file_get_contents(self::EXAMPLES.'/pf2e-extract.json'), Syntax::Json, $this->orc);

        self::assertSame('pf2e', $system->key);
        self::assertSame('Pathfinder Second Edition', $system->name());
        self::assertNull($system->version);
        self::assertSame(['Strength', 'Str', null, null], [$system->attribute('strength')->names->in('en'), $system->attribute('strength')->abbreviations->in('en'), $system->attribute('strength')->minimum, $system->attribute('strength')->maximum]);
        self::assertSame('Climbing, jumping, swimming.', $system->term('skill.athletics')->descriptions->in('en'));
        self::assertSame(['skill', 'condition', 'trait'], $system->categories());
        self::assertSame(['check', 'fortune', 'misfortune'], array_map(static fn (DiceExpression $dice): string => $dice->key, $system->diceExpressions()));
        self::assertSame('Misfortune', $system->dice('misfortune')->names->in('en'));
        self::assertSame('Check', $system->dice('check')->names->in('en'));
        self::assertSame('Fortune', $system->dice('fortune')->names->in('en'));
    }

    public function testTheSrdProfilesStateTheirVariantsAndDifficultyNumbers(): void
    {
        $srd51 = new Importer()->importText(ImportProfile::srd51(), (string) file_get_contents(self::EXAMPLES.'/srd-5.1-extract.json'), Syntax::Json, $this->srd);

        self::assertSame(['encumbrance', 'skills-with-different-abilities'], array_map(static fn (RuleVariant $variant): string => $variant->key, $srd51->variants()));
        self::assertSame(['Encumbrance', 'Skills with Different Abilities'], array_map(static fn (RuleVariant $variant): ?string => $variant->names->in('en'), $srd51->variants()));
        self::assertSame(['check'], array_map(static fn (Difficulty $numbers): string => $numbers->key, $srd51->difficulties()));
        self::assertSame([], $srd51->partyRoles());

        $srd52 = new Importer()->importText(ImportProfile::srd52(), (string) file_get_contents(self::EXAMPLES.'/srd-5.2-extract.json'), Syntax::Json, $this->srd);
        self::assertSame([], $srd52->variants());
        self::assertSame([], $srd52->partyRoles());

        foreach ([$srd51, $srd52] as $system) {
            $check = $system->difficulty('check');
            self::assertSame(['check', 'Typical Difficulty Classes', []], [$check->method, $check->names->in('en'), $check->numbers]);
            self::assertSame(['very-easy', 'easy', 'medium', 'hard', 'very-hard', 'nearly-impossible'], $check->bandKeys());
            self::assertSame([5, 10, 15, 20, 25, 30], array_map(static fn (DifficultyBand $band): ?int => $band->number, $check->bands));
            self::assertSame('Nearly impossible', $check->band('nearly-impossible')?->names->in('en'));
            self::assertSame($this->srd, $check->provenance);
        }

        $encounter = $srd52->difficulty('encounter');
        self::assertSame(['budget', 'XP Budget per Character', ['low', 'moderate', 'high']], [$encounter->method, $encounter->names->in('en'), $encounter->bandKeys()]);
        self::assertSame(['Low', 'Moderate', 'High'], array_map(static fn (DifficultyBand $band): ?string => $band->names->in('en'), $encounter->bands));
        self::assertSame([null, null, null], array_map(static fn (DifficultyBand $band): ?int => $band->number, $encounter->bands));
        self::assertSame(['budgets', 'costs'], array_keys($encounter->numbers));
        self::assertSame(range(1, 20), array_keys((array) $encounter->number('budgets')));
        self::assertSame(['low' => 50, 'moderate' => 75, 'high' => 100], $encounter->number('budgets', 1));
        self::assertSame(['low' => 150, 'moderate' => 225, 'high' => 400], $encounter->number('budgets', 3));
        self::assertSame(['low' => 6_400, 'moderate' => 13_200, 'high' => 22_000], $encounter->number('budgets', 20));
        self::assertCount(34, (array) $encounter->number('costs'));
        self::assertSame([10, 25, 50, 100, 200, 1_100, 155_000], [$encounter->number('costs', 0), $encounter->number('costs', '1/8'), $encounter->number('costs', '1/4'), $encounter->number('costs', '1/2'), $encounter->number('costs', 1), $encounter->number('costs', 4), $encounter->number('costs', 30)]);
    }

    public function testTheSrdEncounterBudgetsAndCostsAreTheOnesOfTheDocument(): void
    {
        $srd52 = new Importer()->import(ImportProfile::srd52(), [], $this->srd);
        $levels = [
            [50, 75, 100], [100, 150, 200], [150, 225, 400], [250, 375, 500], [500, 750, 1_100],
            [600, 1_000, 1_400], [750, 1_300, 1_700], [1_000, 1_700, 2_100], [1_300, 2_000, 2_600], [1_600, 2_300, 3_100],
            [1_900, 2_900, 4_100], [2_200, 3_700, 4_700], [2_600, 4_200, 5_400], [2_900, 4_900, 6_200], [3_300, 5_400, 7_800],
            [3_800, 6_100, 9_800], [4_500, 7_200, 11_700], [5_000, 8_700, 14_200], [5_500, 10_700, 17_200], [6_400, 13_200, 22_000],
        ];
        $budgets = [];
        foreach ($levels as $index => [$low, $moderate, $high]) {
            $budgets[$index + 1] = ['low' => $low, 'moderate' => $moderate, 'high' => $high];
        }

        $costs = [
            '0' => 10, '1/8' => 25, '1/4' => 50, '1/2' => 100, 1 => 200, 2 => 450, 3 => 700, 4 => 1_100,
            5 => 1_800, 6 => 2_300, 7 => 2_900, 8 => 3_900, 9 => 5_000, 10 => 5_900, 11 => 7_200,
            12 => 8_400, 13 => 10_000, 14 => 11_500, 15 => 13_000, 16 => 15_000, 17 => 18_000,
            18 => 20_000, 19 => 22_000, 20 => 25_000, 21 => 33_000, 22 => 41_000, 23 => 50_000,
            24 => 62_000, 25 => 75_000, 26 => 90_000, 27 => 105_000, 28 => 120_000, 29 => 135_000,
            30 => 155_000,
        ];

        self::assertSame($budgets, $srd52->difficulty('encounter')->number('budgets'));
        self::assertSame($costs, $srd52->difficulty('encounter')->number('costs'));
    }

    public function testThePathfinderProfileStatesItsVariantRulesAndDifficultyNumbers(): void
    {
        $system = new Importer()->importText(ImportProfile::pathfinder2e(), (string) file_get_contents(self::EXAMPLES.'/pf2e-extract.json'), Syntax::Json, $this->orc);

        self::assertSame(['automatic-bonus-progression', 'dual-class', 'free-archetype', 'proficiency-without-level'], array_map(static fn (RuleVariant $variant): string => $variant->key, $system->variants()));
        self::assertSame('Dual-Class Characters', $system->variant('dual-class')->names->in('en'));
        self::assertSame(['simple', 'level', 'encounter'], array_map(static fn (Difficulty $numbers): string => $numbers->key, $system->difficulties()));
        self::assertSame([], array_map(static fn (PartyRole $role): string => $role->key, $system->partyRoles()));

        $simple = $system->difficulty('simple');
        self::assertSame(['check', 'Simple DCs'], [$simple->method, $simple->names->in('en')]);
        self::assertSame(['untrained' => 10, 'trained' => 15, 'expert' => 20, 'master' => 30, 'legendary' => 40], array_combine($simple->bandKeys(), array_map(static fn (DifficultyBand $band): ?int => $band->number, $simple->bands)));

        $level = $system->difficulty('level');
        self::assertSame(['level-dc', 'DCs by Level'], [$level->method, $level->names->in('en')]);
        self::assertSame(['incredibly-easy' => -10, 'very-easy' => -5, 'easy' => -2, 'hard' => 2, 'very-hard' => 5, 'incredibly-hard' => 10], array_combine($level->bandKeys(), array_map(static fn (DifficultyBand $band): ?int => $band->number, $level->bands)));
        self::assertSame([14, 15, 16, 18, 19, 20, 22, 23, 24, 26, 27, 28, 30, 31, 32, 34, 35, 36, 38, 39, 40, 42, 44, 46, 48, 50], $level->number('levels'));
        self::assertSame([14, 15, 18, 30, 40, 50], [$level->number('levels', 0), $level->number('levels', 1), $level->number('levels', 3), $level->number('levels', 12), $level->number('levels', 20), $level->number('levels', 25)]);

        $encounter = $system->difficulty('encounter');
        self::assertSame(['level', 'Encounter Budget', ['trivial', 'low', 'moderate', 'severe', 'extreme']], [$encounter->method, $encounter->names->in('en'), $encounter->bandKeys()]);
        self::assertSame([
            'party-size' => 4,
            'budgets' => ['trivial' => 40, 'low' => 60, 'moderate' => 80, 'severe' => 120, 'extreme' => 160],
            'adjustments' => ['trivial' => 10, 'low' => 15, 'moderate' => 20, 'severe' => 30, 'extreme' => 40],
            'costs' => [-4 => 10, -3 => 15, -2 => 20, -1 => 30, 0 => 40, 1 => 60, 2 => 80, 3 => 120, 4 => 160],
        ], $encounter->numbers);
        self::assertSame($this->orc, $encounter->provenance);
    }

    public function testAProfileOfItsOwnStatesItsPlayRecords(): void
    {
        $profile = new ImportProfile('custom', 'Système', [Licence::CcBy40], locale: 'fr', variants: ['nuit' => 'La nuit'], difficulties: [
            ['key' => 'jet', 'name' => 'Difficulté', 'description' => 'Un jet.', 'bands' => [['key' => 'facile', 'name' => 'Facile', 'number' => 4], ['key' => 'dur', 'name' => 'Dur']], 'numbers' => ['niveaux' => [1, 2]]],
            ['key' => 'nu', 'name' => 'Nu'],
        ], partyRoles: ['guide' => 'Guide']);
        $system = new Importer()->import($profile, [], $this->srd);

        self::assertSame(['La nuit', 'Guide'], [$system->variant('nuit')->names->in('fr'), $system->partyRole('guide')->names->in('fr')]);
        self::assertSame([$this->srd, $this->srd], [$system->variant('nuit')->provenance, $system->partyRole('guide')->provenance]);
        $jet = $system->difficulty('jet');
        self::assertSame(['Difficulté', 'Un jet.', null, ['facile', 'dur'], 4, null, 'Facile', ['niveaux' => [1, 2]]], [$jet->names->in('fr'), $jet->descriptions->in('fr'), $jet->method, $jet->bandKeys(), $jet->band('facile')?->number, $jet->band('dur')?->number, $jet->band('facile')?->names->in('fr'), $jet->numbers]);
        $nu = $system->difficulty('nu');
        self::assertSame([null, [], [], true], [$nu->method, $nu->bands, $nu->numbers, $nu->descriptions->isEmpty()]);

        $other = $profile->withVersion('2');
        self::assertSame([$profile->variants, $profile->difficulties, $profile->partyRoles], [$other->variants, $other->difficulties, $other->partyRoles]);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The key of a rule variant "Nuit" must be');
        new Importer()->import(new ImportProfile('custom', 'Système', [Licence::CcBy40], variants: ['Nuit' => 'Nuit']), [], $this->srd);
    }

    public function testAProfileForAnotherVersionKeepsEverythingElse(): void
    {
        $profile = new ImportProfile('custom', 'Mon système', [Licence::CcBy40, Licence::CcBySa40], '1', new TableMapping('caracteristiques', key: 'id', name: 'nom'), [new TableMapping('etats', 'condition', 'id', 'nom')], ['test' => '1d20'], ['test' => 'Test'], 'fr', 1, 20);
        $other = $profile->withVersion('1.1');

        self::assertSame('1.1', $other->version);
        self::assertSame('1', $profile->version);
        self::assertEquals(new ImportProfile('custom', 'Mon système', [Licence::CcBy40, Licence::CcBySa40], '1.1', new TableMapping('caracteristiques', key: 'id', name: 'nom'), [new TableMapping('etats', 'condition', 'id', 'nom')], ['test' => '1d20'], ['test' => 'Test'], 'fr', 1, 20), $other);

        $system = new Importer()->importText(ImportProfile::srd52()->withVersion('5.2.1'), (string) file_get_contents(self::EXAMPLES.'/srd-5.2-extract.json'), Syntax::Json, $this->srd);
        self::assertSame(['srd-5.2', '5.2.1'], [$system->key, $system->version]);

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The version of the profile "custom" is required.');
        $profile->withVersion(' ');
    }

    public function testASourceUnderAnotherLicenceIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The profile of "srd-5.2" imports material under CC-BY-4.0, not ORC.');

        new Importer()->import(ImportProfile::srd52(), [], $this->orc);
    }

    public function testAProfileOfItsOwnReadsOtherFields(): void
    {
        $profile = new ImportProfile(
            'custom',
            'Système',
            [Licence::CcBy40, Licence::CcBySa40],
            null,
            new TableMapping('caracs', key: 'id', name: 'nom'),
            [new TableMapping('etats', key: 'id', name: 'nom', description: 'texte')],
            ['jet' => '1d10'],
            locale: 'fr',
        );
        $system = new Importer()->import($profile, [
            'caracs' => [['id' => 'Force Brute', 'nom' => 'Force']],
            'etats' => [['id' => 'À terre!', 'nom' => 'À terre', 'texte' => 'Couché.']],
        ], new Provenance('Source', Licence::CcBySa40, 'Source, CC BY-SA 4.0.'));

        self::assertSame('Système', $system->name('fr'));
        self::assertSame('Force', $system->attribute('force-brute')->names->in('fr'));
        self::assertTrue($system->attribute('force-brute')->abbreviations->isEmpty());
        self::assertSame('Couché.', $system->term('term.terre')->descriptions->in('fr'));
        self::assertNull($system->term('term.terre')->category);
        self::assertSame('jet', $system->dice('jet')->names->in('fr'));
        self::assertSame(['jet'], array_map(static fn (DiceExpression $dice): string => $dice->key, new Importer()->import(new ImportProfile('custom', 'S', [Licence::Cc0], dice: ['jet' => '1d10']), [], new Provenance('S', Licence::Cc0))->diceExpressions()));
        self::assertSame([], new Importer()->import(new ImportProfile('custom', 'S', [Licence::Cc0]), ['caracs' => [['x' => 1]]], new Provenance('S', Licence::Cc0))->attributes());
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function brokenExtracts(): iterable
    {
        yield 'a table as a mapping' => [['ability-scores' => ['str' => []]], 'ability-scores: a list is expected.'];
        yield 'a record as text' => [['conditions' => ['blinded']], 'conditions[0]: a mapping of names to values is expected.'];
        yield 'no key' => [['skills' => [['name' => 'Acrobatics']]], 'skills[0].index: a text is expected.'];
        yield 'no name' => [['conditions' => [['index' => 'x']]], 'conditions[0].name: a text is expected.'];
        yield 'no abbreviation' => [['ability-scores' => [['index' => 'str', 'full_name' => 'Strength']]], 'ability-scores[0].name: a text is expected.'];
        yield 'a key of nothing' => [['skills' => [['index' => '!!', 'name' => 'Nothing']]], 'skills[0]: The key of a term "skill." must be'];
        yield 'a description of numbers' => [['conditions' => [['index' => 'x', 'name' => 'X', 'desc' => [1]]]], 'conditions[0].desc: a text or a list of texts is expected.'];
        yield 'a description as a mapping' => [['conditions' => [['index' => 'x', 'name' => 'X', 'desc' => ['a' => 'b']]]], 'conditions[0].desc: a list is expected.'];
        yield 'too long a description' => [['conditions' => [['index' => 'x', 'name' => 'X', 'desc' => [str_repeat('a', 2000), str_repeat('b', 2000)]]]], 'conditions[0]: A text in "en" is longer than 4000 characters.'];
        yield 'two records of one key' => [['skills' => [['index' => 'a', 'name' => 'A'], ['index' => 'A', 'name' => 'B']]], 'The system "srd-5.2" holds two of the term "skill.a".'];
        yield 'a list for an extract' => [[['index' => 'a']], 'A mapping of names to values is expected.'];
    }

    /**
     * @param array<mixed> $extract
     */
    #[DataProvider('brokenExtracts')]
    public function testABrokenExtractIsRefusedWithThePlaceOfTheMistake(array $extract, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Importer()->import(ImportProfile::srd52(), $extract, $this->srd);
    }

    public function testAProfileAcceptsALicenceAndCategoriesThatAreKeys(): void
    {
        try {
            new ImportProfile('x', 'X', []);
            self::fail('A profile without a licence was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The profile of "x" accepts no licence.', $invalidRecord->getMessage());
        }

        try {
            new ImportProfile('X', 'X', [Licence::Cc0]);
            self::fail('A profile with a bad key was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertStringStartsWith('The key of a system "X"', $invalidRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The key of the category of a table "Condition"');

        new TableMapping('conditions', 'Condition');
    }

    public function testABrokenNotationInAProfileIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The notation "2d" of the dice expression "d" cannot be read');

        new Importer()->import(new ImportProfile('x', 'X', [Licence::Cc0], dice: ['d' => '2d']), [], new Provenance('X', Licence::Cc0));
    }
}
