<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Format\Fields;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Import\Importer;
use Tilttek\RpgSystems\Import\ImportProfile;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

/**
 * The boundaries and the exact values the other tests do not pin: what the mutation testing found alive.
 */
#[CoversClass(Attribute::class)]
#[CoversClass(AttributionRenderer::class)]
#[CoversClass(DiceExpression::class)]
#[CoversClass(Fields::class)]
#[CoversClass(GameSystem::class)]
#[CoversClass(ImportProfile::class)]
#[CoversClass(Importer::class)]
#[CoversClass(InvalidDocument::class)]
#[CoversClass(SystemReader::class)]
#[CoversClass(SystemWriter::class)]
final class MutationTest extends TestCase
{
    public function testTheKeyOfARecordIsChecked(): void
    {
        $own = new Provenance('Own', Licence::Owned);

        foreach ([
            static fn (): Attribute => new Attribute('Not A Key', Translations::of(['en' => 'A']), $own),
            static fn (): DiceExpression => new DiceExpression('Not A Key', '1d6', Translations::of(['en' => 'A']), $own),
        ] as $make) {
            try {
                $make();
                self::fail('A bad key was accepted.');
            } catch (InvalidRecord $invalidRecord) {
                self::assertStringContainsString('"Not A Key" must be', $invalidRecord->getMessage());
            }
        }
    }

    public function testAnExceptionCarriesNoCode(): void
    {
        self::assertSame(0, InvalidDocument::at('a.b', 'is wrong')->getCode());
        self::assertSame(0, InvalidDocument::at('', 'is wrong')->getCode());
    }

    public function testASourceIsShownOnceAndItsFirstInstanceIsKept(): void
    {
        $first = new Provenance('Terms', Licence::CcBy40, 'Terms, CC BY 4.0.');
        $again = new Provenance('Terms', Licence::CcBy40, 'Terms, CC BY 4.0.');

        $sources = new AttributionRenderer()->sources([$first, $again]);

        self::assertCount(1, $sources);
        self::assertSame($first, $sources[0]);
    }

    public function testTheSourcesOfASystemAreItsOwnThenItsRecordsThenItsTranslations(): void
    {
        $own = new Provenance('Own', Licence::Owned);
        $forAttribute = new Provenance('For attribute', Licence::CcBy40, 'A, CC BY 4.0.');
        $forDice = new Provenance('For dice', Licence::CcBy40, 'D, CC BY 4.0.');
        $forTerm = new Provenance('For term', Licence::CcBy40, 'T, CC BY 4.0.');
        $translation = new Provenance('Translation', Licence::CcBy40, 'Tr, CC BY 4.0.');
        $names = Translations::of(['en' => 'N']);
        $system = new GameSystem(
            'lanterns',
            $names,
            $own,
            null,
            [new Attribute('grit', $names, $forAttribute), new Attribute('wits', $names, $own)],
            [new DiceExpression('action', '2d6', $names, $forDice)],
            [new Term('free', $names, $forTerm)],
            ['es' => [$translation]],
        );
        $same = new Provenance('For attribute', Licence::CcBy40, 'A, CC BY 4.0.');

        self::assertSame([$own, $forAttribute, $forDice, $forTerm, $translation], $system->provenances());
        self::assertSame([$own, $forAttribute, $forDice, $forTerm], $system->provenances('fr'));

        $duplicated = new GameSystem('lanterns', $names, $own, null, [new Attribute('grit', $names, $forAttribute), new Attribute('wits', $names, $same)]);
        self::assertSame([$own, $forAttribute], $duplicated->provenances());
    }

    public function testAnApostropheAndAnInvalidTextAreEscapedForHtml(): void
    {
        $html = new AttributionRenderer()->html([new Provenance("Ranger's guide", Licence::CcBy40, "Ranger's guide, CC BY 4.0.")]);

        self::assertStringContainsString('Ranger&apos;s guide, CC BY 4.0.', $html);
        self::assertStringNotContainsString('&#039;', $html);
    }

    public function testASystemWithoutVersionIsWrittenWithoutIt(): void
    {
        $names = Translations::of(['en' => 'N', 'es' => 'Acción']);
        $system = new GameSystem('lanterns', $names, new Provenance('Own', Licence::Owned), null, [], [], [new Term('free', $names, new Provenance('Own', Licence::Owned), null, Translations::of(['en' => "Line one\nLine two"]))]);
        $writer = new SystemWriter();

        self::assertSame(['key' => 'lanterns', 'names' => ['en' => 'N', 'es' => 'Acción']], $writer->toArray($system)['system']);
        self::assertStringContainsString('"es": "Acción"', $writer->toJson($system));
        self::assertStringContainsString('      en: |-', $writer->toYaml($system));
    }

    public function testTheYamlOfTheFullDocumentIsTheSameAtEveryDepth(): void
    {
        // The deepest mapping of a document is three levels down: the inline depth of the dump is never reached.
        $reader = new SystemReader();
        $names = Translations::of(['en' => 'N']);
        $own = new Provenance('Own', Licence::Owned);
        $system = new GameSystem('lanterns', $names, $own, '1', [new Attribute('grit', $names, new Provenance('Other', Licence::CcBy40, 'O, CC BY 4.0.'))], [], [], ['es' => [$own]]);
        $yaml = new SystemWriter()->toYaml($system);

        self::assertStringNotContainsString('{', $yaml);
        self::assertEquals($system, $reader->system($reader->decode($yaml, Syntax::Yaml)));
    }

    public function testAJsonDocumentMayBe63LevelsDeep(): void
    {
        $reader = new SystemReader();

        self::assertIsArray($reader->decode(str_repeat('[', 63).str_repeat(']', 63), Syntax::Json));

        $this->expectException(InvalidDocument::class);
        $reader->decode(str_repeat('[', 64).str_repeat(']', 64), Syntax::Json);
    }

    public function testAListMayHoldItsMaximum(): void
    {
        $fields = Fields::of(['items' => [1, 2, 3]], '');

        self::assertSame([1, 2, 3], $fields->list('items', 3));

        $this->expectException(InvalidDocument::class);
        $fields->list('items', 2);
    }

    public function testTheTranslationsOfALocaleMayBe64AndAreAllKept(): void
    {
        $reader = new SystemReader();
        $source = static fn (int $index): array => ['title' => 'T'.$index, 'licence' => 'owned'];
        $document = ['format' => SystemReader::FORMAT, 'system' => ['key' => 'x', 'names' => 'X'], 'source' => ['title' => 'X', 'licence' => 'owned']];

        $system = $reader->system($document + ['translations' => ['es' => array_map($source, range(1, 64)), 'de' => array_map($source, range(1, 2))]]);
        self::assertSame([64, 2], [\count($system->translationSources['es']), \count($system->translationSources['de'])]);
        self::assertSame(['es', 'de'], array_keys($system->translationSources));

        $this->expectException(InvalidDocument::class);
        $reader->system($document + ['translations' => ['es' => array_map($source, range(1, 65))]]);
    }

    public function testALocalisationNamesItsSystemAndKeepsOnlyTheTextsItGives(): void
    {
        $reader = new SystemReader();
        $document = ['format' => SystemReader::FORMAT, 'kind' => 'localisation', 'locale' => 'de', 'source' => ['title' => 'T', 'licence' => 'owned']];

        $localisation = $reader->localisation($document + [
            'system' => 'lanterns',
            'attributes' => ['grit' => ['name' => 'Mut'], 'wits' => ['abbreviation' => 'VER', 'description' => 'D']],
            'terms' => ['free' => ['description' => 'Frei'], 'lit' => ['name' => 'Hell']],
        ]);

        self::assertSame(['grit' => ['name' => 'Mut'], 'wits' => ['abbreviation' => 'VER', 'description' => 'D']], $localisation->attributes);
        self::assertSame(['free' => ['description' => 'Frei'], 'lit' => ['name' => 'Hell']], $localisation->terms);

        $this->expectException(InvalidDocument::class);
        $reader->localisation($document);
    }

    public function testASystemsKeepsTheOrderAndReplacesBySameKey(): void
    {
        $names = Translations::of(['en' => 'N']);
        $own = new Provenance('Own', Licence::Owned);
        $a = new GameSystem('a', $names, $own);
        $b = new GameSystem('b', $names, $own);
        $a2 = new GameSystem('a', Translations::of(['en' => 'A2']), $own);
        $systems = new Systems($a, $b);

        self::assertSame([$a2, $b], $systems->with($a2)->all());
        self::assertSame([$a, $b], $systems->all());
    }

    public function testTheSrdProfileNamesItsDiceAndReadsTheLongestListOfParagraphs(): void
    {
        $source = new Provenance('SRD', Licence::CcBy40, 'SRD, CC BY 4.0.');
        $importer = new Importer();
        $system = $importer->import(ImportProfile::srd52(), [], $source);

        self::assertSame('D20 Test', $system->dice('d20-test')->names->in('en'));

        $table = static fn (int $paragraphs): array => ['skills' => [['index' => 'stealth', 'name' => 'Stealth', 'desc' => array_fill(0, $paragraphs, 'Text.')]]];

        self::assertSame(100, substr_count((string) $importer->import(ImportProfile::srd52(), $table(100), $source)->term('skill.stealth')->descriptions->in('en'), 'Text.'));

        $this->expectException(InvalidDocument::class);
        $importer->import(ImportProfile::srd52(), $table(101), $source);
    }
}
