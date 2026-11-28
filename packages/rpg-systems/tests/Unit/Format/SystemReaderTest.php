<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Format\Fields;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Licence;

#[CoversClass(SystemReader::class)]
#[CoversClass(Fields::class)]
#[CoversClass(Syntax::class)]
#[CoversClass(InvalidDocument::class)]
final class SystemReaderTest extends TestCase
{
    private const string EXAMPLES = __DIR__.'/../../../examples';

    public function testASystemIsReadWithTheDocumentsSourceOrItsRecordsOwn(): void
    {
        $system = new SystemReader()->system($this->document());

        self::assertSame('lanterns', $system->key);
        self::assertSame('5.2', $system->version);
        self::assertSame(['fr' => 'Lanternes'], $system->names->texts);
        self::assertSame('Lanterns', $system->provenance->title);
        self::assertSame(Licence::Owned, $system->provenance->licence);
        $grit = $system->attribute('grit');
        self::assertSame(['fr' => 'Cran'], $grit->names->texts);
        self::assertSame(['fr' => 'CRN', 'en' => 'GRT'], $grit->abbreviations->texts);
        self::assertSame(['fr' => 'Tenir.'], $grit->descriptions->texts);
        self::assertSame([1, 6], [$grit->minimum, $grit->maximum]);
        self::assertSame($system->provenance, $grit->provenance);
        self::assertSame('3d6kl2', $system->dice('desperate')->notation);
        $near = $system->term('range.near');
        self::assertSame('range', $near->category);
        self::assertSame(['fr' => 'Proche à portée.'], $near->descriptions->texts);
        self::assertSame('Open ranges', $near->provenance->title);
        self::assertSame(Licence::CcBy40, $near->provenance->licence);
        self::assertSame('Ranges, CC BY 4.0.', $near->provenance->attribution);
        self::assertSame('Someone', $near->provenance->author);
        self::assertSame('https://example.org/ranges', $near->provenance->url?->toString());
        self::assertSame(['es'], array_keys($system->translationSources));
        self::assertSame('Traducción', $system->translationSources['es'][0]->title);
        self::assertSame('en', new SystemReader()->system(['format' => SystemReader::FORMAT, 'system' => ['key' => 'x', 'names' => 'X'], 'source' => ['title' => 'X', 'licence' => 'owned']])->names->locales()[0]);
    }

    public function testYamlAndJsonAreDecoded(): void
    {
        $reader = new SystemReader();

        self::assertSame(['format' => 'x', 'list' => [1, 2]], $reader->decode("format: x\nlist: [1, 2]\n", Syntax::Yaml));
        self::assertSame(['format' => 'x'], $reader->decode('{"format": "x"}', Syntax::Json));
        self::assertSame(Syntax::Yaml, Syntax::ofPath('a/b.YAML'));
        self::assertSame(Syntax::Yaml, Syntax::ofPath('b.yml'));
        self::assertSame(Syntax::Json, Syntax::ofPath('b.json'));

        foreach ([
            ["a: !php/object 'O:8:\"stdClass\":0:{}'", Syntax::Yaml, 'The document is not valid YAML: '],
            ['a: !custom x', Syntax::Yaml, 'The document is not valid YAML: '],
            ['{"a": ', Syntax::Json, 'The document is not valid JSON: '],
            [str_repeat('[', 65).str_repeat(']', 65), Syntax::Json, 'The document is not valid JSON: '],
            ['just text', Syntax::Yaml, 'The document is not a mapping.'],
            [str_repeat(' ', 2_000_001), Syntax::Yaml, 'The document is longer than 2000000 bytes.'],
        ] as [$text, $syntax, $message]) {
            try {
                new SystemReader()->decode($text, $syntax);
                self::fail(\sprintf('"%s" was decoded.', $text));
            } catch (InvalidDocument $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertSame([], new SystemReader(2)->decode('{}', Syntax::Json));

        $this->expectExceptionMessageIsOrContains('b.txt: a document of the format ends in .yaml, .yml or .json.');

        Syntax::ofPath('b.txt');
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function brokenDocuments(): iterable
    {
        $document = self::valid();

        yield 'no format' => [['format' => null] + $document, 'format: the document must declare "format: tilttek-rpg-systems/1".'];
        yield 'another format' => [['format' => 'tilttek-rpg-systems/2'] + $document, 'format: the document must declare'];
        yield 'another kind' => [['kind' => 'localisation'] + $document, 'kind: a document of the kind "system" is expected.'];
        yield 'an unknown field' => [$document + ['extra' => 1], '"extra" is not one of format, kind, locale, system, source, attributes, dice, terms, variants, difficulties, roles, translations.'];
        yield 'a list for a document' => [[1, 2], 'A mapping of names to values is expected.'];
        yield 'a numbered field' => [$document + [3 => 'x'], 'The name "3" is not a text.'];
        yield 'no system' => [['system' => null] + $document, 'system.key: a text is expected.'];
        yield 'a list of names' => [['system' => ['key' => 'x', 'names' => ['X']]] + $document, 'system.names: a mapping of names to values is expected.'];
        yield 'an unknown licence' => [['source' => ['title' => 'X', 'licence' => 'OGL-1.0a']] + $document, 'source.licence: the licence must be one of CC-BY-4.0, CC-BY-3.0, CC-BY-SA-4.0, CC0-1.0, ORC, owned.'];
        yield 'an unknown source field' => [['source' => ['title' => 'X', 'licence' => 'owned', 'year' => 2026]] + $document, 'source: "year" is not one of title, licence, attribution, author, url.'];
        yield 'a source without attribution' => [['source' => ['title' => 'X', 'licence' => 'CC-BY-4.0']] + $document, 'source: The attribution of the source "X" is required.'];
        yield 'attributes as a mapping' => [['attributes' => ['grit' => []]] + $document, 'attributes: a list is expected.'];
        yield 'an attribute as text' => [['attributes' => ['grit']] + $document, 'attributes[0]: a mapping of names to values is expected.'];
        yield 'a bound as text' => [['attributes' => [['key' => 'grit', 'names' => 'Grit', 'minimum' => '1']]] + $document, 'attributes[0].minimum: a whole number is expected.'];
        yield 'an unknown attribute field' => [['attributes' => [['key' => 'grit', 'names' => 'Grit', 'rank' => 1]]] + $document, 'attributes[0]: "rank" is not one of key, names, abbreviations, descriptions, minimum, maximum, source.'];
        yield 'an attribute without a name' => [['attributes' => [['key' => 'grit']]] + $document, 'attributes[0]: The attribute "grit" has no name.'];
        yield 'a broken notation' => [['dice' => [['key' => 'd', 'names' => 'D', 'notation' => '2d']]] + $document, 'dice[0]: The notation "2d" of the dice expression "d" cannot be read: '];
        yield 'an unknown dice field' => [['dice' => [['key' => 'd', 'names' => 'D', 'notation' => '2d6', 'faces' => 6]]] + $document, 'dice[0]: "faces" is not one of key, notation, names, source.'];
        yield 'an unknown term field' => [['terms' => [['key' => 't', 'names' => 'T', 'page' => 3]]] + $document, 'terms[0]: "page" is not one of key, names, category, descriptions, source.'];
        yield 'a term name as a list' => [['terms' => [['key' => 't', 'names' => ['en' => ['T']]]]] + $document, 'terms[0].names.en: a text is expected.'];
        yield 'a bad locale' => [['terms' => [['key' => 't', 'names' => ['english' => 'T']]]] + $document, 'terms[0].names: "english" is not a locale'];
        yield 'a record source broken' => [['terms' => [['key' => 't', 'names' => 'T', 'source' => ['licence' => 'owned']]]] + $document, 'terms[0].source.title: a text is expected.'];
        yield 'a translation source as a mapping' => [['translations' => ['es' => ['title' => 'X']]] + $document, 'translations.es: a list is expected.'];
        yield 'a translation source broken' => [['translations' => ['es' => [['title' => 'X', 'licence' => 'ORC']]]] + $document, 'translations.es[0]: The attribution of the source "X" is required.'];
        yield 'two records of one key' => [['terms' => [['key' => 't', 'names' => 'T'], ['key' => 't', 'names' => 'U']]] + $document, 'The system "x" holds two of the term "t".'];
    }

    /**
     * @param array<mixed> $document
     */
    #[DataProvider('brokenDocuments')]
    public function testABrokenDocumentIsRefusedWithThePlaceOfTheMistake(array $document, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SystemReader()->system($document);
    }

    public function testTooManyRecordsAreRefusedBeforeBeingRead(): void
    {
        $this->expectExceptionMessageIsOrContains('terms: more than 10000 items.');

        new SystemReader()->system(['terms' => array_fill(0, 10_001, 'x')] + self::valid());
    }

    public function testALocalisationIsReadAndLaidOverItsSystem(): void
    {
        $reader = new SystemReader();
        $system = $reader->system($this->document());
        $localised = $reader->localise($system, [
            'format' => SystemReader::FORMAT,
            'kind' => 'localisation',
            'system' => 'lanterns',
            'locale' => 'de',
            'source' => ['title' => 'Übersetzung', 'licence' => 'CC0-1.0'],
            'name' => 'Laternen',
            'attributes' => ['grit' => ['name' => 'Mut', 'abbreviation' => 'MUT', 'description' => 'Halten.'], 'wits' => ['abbreviation' => 'VER']],
            'dice' => ['desperate' => 'Verzweifelt'],
            'terms' => ['range.near' => ['name' => 'Nah', 'description' => 'In der Nähe.'], 'condition.lit' => ['description' => 'Beleuchtet.']],
        ]);

        self::assertSame('Laternen', $localised->name('de'));
        self::assertSame(['fr' => 'Cran', 'de' => 'Mut'], $localised->attribute('grit')->names->texts);
        self::assertSame('MUT', $localised->attribute('grit')->abbreviations->in('de'));
        self::assertSame('Halten.', $localised->attribute('grit')->descriptions->in('de'));
        self::assertSame(['fr' => 'Esprit'], $localised->attribute('wits')->names->texts);
        self::assertSame('VER', $localised->attribute('wits')->abbreviations->in('de'));
        self::assertSame('Verzweifelt', $localised->dice('desperate')->names->in('de'));
        self::assertSame('Nah', $localised->term('range.near')->names->in('de'));
        self::assertSame('In der Nähe.', $localised->term('range.near')->descriptions->in('de'));
        self::assertSame(['fr' => 'Éclairé'], $localised->term('condition.lit')->names->texts);
        self::assertSame('Beleuchtet.', $localised->term('condition.lit')->descriptions->in('de'));
        self::assertSame('Übersetzung', $localised->translationSources['de'][0]->title);

        $minimal = $reader->localisation(['format' => SystemReader::FORMAT, 'kind' => 'localisation', 'system' => 'lanterns', 'locale' => 'it', 'source' => ['title' => 'T', 'licence' => 'owned']]);
        self::assertSame('it', $minimal->locale);
        self::assertNull($minimal->systemName);
        self::assertSame([[], [], []], [$minimal->attributes, $minimal->dice, $minimal->terms]);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function brokenLocalisations(): iterable
    {
        $localisation = ['format' => SystemReader::FORMAT, 'kind' => 'localisation', 'system' => 'lanterns', 'locale' => 'de', 'source' => ['title' => 'T', 'licence' => 'owned']];

        yield 'another system' => [['system' => 'other'] + $localisation, 'system: the localisation is of the system "other", not "lanterns".'];
        yield 'no locale' => [['locale' => null] + $localisation, 'locale: a text is expected.'];
        yield 'no system' => [['system' => null] + $localisation, 'system: a text is expected.'];
        yield 'a system kind' => [['kind' => 'system'] + $localisation, 'kind: a document of the kind "localisation" is expected.'];
        yield 'an unknown field' => [$localisation + ['version' => '1'], '"version" is not one of format, kind, locale, system, source, name, attributes, dice, terms, variants, difficulties, roles.'];
        yield 'an unknown attribute' => [['attributes' => ['glow' => ['name' => 'Glanz']]] + $localisation, 'The system "lanterns" has no attribute with the key "glow".'];
        yield 'an unknown attribute text' => [['attributes' => ['grit' => ['title' => 'Mut']]] + $localisation, 'attributes.grit: "title" is not one of name, abbreviation, description.'];
        yield 'an unknown term text' => [['terms' => ['range.near' => ['abbreviation' => 'N']]] + $localisation, 'terms.range.near: "abbreviation" is not one of name, description.'];
        yield 'a dice name as a list' => [['dice' => ['desperate' => ['x']]] + $localisation, 'dice.desperate: a text is expected.'];
        yield 'a bad locale' => [['locale' => 'deutsch'] + $localisation, '"deutsch" is not a locale'];
    }

    /**
     * @param array<mixed> $document
     */
    #[DataProvider('brokenLocalisations')]
    public function testABrokenLocalisationIsRefused(array $document, string $message): void
    {
        $reader = new SystemReader();
        $system = $reader->system($this->document());

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        $reader->localise($system, $document);
    }

    public function testFilesAreLoadedSystemsFirstThenLocalisations(): void
    {
        $systems = new SystemReader()->load(self::EXAMPLES.'/lanterns.es.yaml', self::EXAMPLES.'/lanterns.yaml');
        $lanterns = $systems->get('lanterns');

        self::assertSame('Linternas en la oscuridad', $lanterns->name('es'));
        self::assertSame('Lanternes dans le noir', $lanterns->name('fr'));
        self::assertSame('Temple', $lanterns->attribute('grit')->names->in('es'));
        self::assertCount(1, $systems->all());

        $directory = sys_get_temp_dir().'/rpg-systems-'.bin2hex(random_bytes(4));
        mkdir($directory);

        try {
            file_put_contents($directory.'/lanterns.json', json_encode(new SystemReader()->decode((string) file_get_contents(self::EXAMPLES.'/lanterns.yaml'), Syntax::Yaml), \JSON_THROW_ON_ERROR));
            file_put_contents($directory.'/orphan.yaml', "format: tilttek-rpg-systems/1\nkind: localisation\nsystem: missing\nlocale: de\nsource: {title: T, licence: owned}\n");
            self::assertSame('Lanterns in the Dark', new SystemReader()->load($directory.'/lanterns.json')->get('lanterns')->name());

            foreach ([
                [[$directory.'/lanterns.json', $directory.'/orphan.yaml'], 'orphan.yaml: the localisation names the system "missing", which no file holds.'],
                [[$directory.'/none.yaml'], 'none.yaml: the file cannot be read.'],
                [[$directory], \sprintf('%s: the file cannot be read.', basename($directory))],
            ] as [$paths, $message]) {
                try {
                    new SystemReader()->load(...$paths);
                    self::fail('The files were loaded.');
                } catch (InvalidDocument $exception) {
                    self::assertStringEndsWith($message, $exception->getMessage());
                }
            }

            $size = (int) filesize($directory.'/lanterns.json');
            self::assertCount(1, new SystemReader($size)->load($directory.'/lanterns.json')->all());

            $this->expectExceptionMessageIsOrContains(\sprintf('lanterns.json: the file is longer than %d bytes.', $size - 1));

            new SystemReader($size - 1)->load($directory.'/lanterns.json');
        } finally {
            foreach (['lanterns.json', 'orphan.yaml'] as $file) {
                unlink($directory.'/'.$file);
            }

            rmdir($directory);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return ['format' => SystemReader::FORMAT, 'system' => ['key' => 'x', 'names' => 'X'], 'source' => ['title' => 'X', 'licence' => 'owned']];
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return [
            'format' => SystemReader::FORMAT,
            'kind' => 'system',
            'locale' => 'fr',
            'system' => ['key' => 'lanterns', 'names' => 'Lanternes', 'version' => 5.2],
            'source' => ['title' => 'Lanterns', 'licence' => 'owned'],
            'attributes' => [
                ['key' => 'grit', 'names' => 'Cran', 'abbreviations' => ['fr' => 'CRN', 'en' => 'GRT'], 'descriptions' => 'Tenir.', 'minimum' => 1, 'maximum' => 6],
                ['key' => 'wits', 'names' => 'Esprit'],
            ],
            'dice' => [['key' => 'desperate', 'notation' => '3d6kl2', 'names' => 'Désespéré']],
            'terms' => [
                ['key' => 'range.near', 'category' => 'range', 'names' => 'Proche', 'descriptions' => 'Proche à portée.', 'source' => [
                    'title' => 'Open ranges', 'licence' => 'CC-BY-4.0', 'attribution' => 'Ranges, CC BY 4.0.', 'author' => 'Someone', 'url' => 'https://example.org/ranges',
                ]],
                ['key' => 'condition.lit', 'names' => 'Éclairé'],
            ],
            'translations' => ['es' => [['title' => 'Traducción', 'licence' => 'CC0-1.0']]],
        ];
    }
}
