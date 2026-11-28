<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Exception\InvalidDocument;
use Tilttek\ReferenceTables\Format\Syntax;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Internal\Fields;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\RpgSystems\Licence;

#[CoversClass(TableReader::class)]
#[CoversClass(Syntax::class)]
#[CoversClass(Fields::class)]
#[CoversClass(InvalidDocument::class)]
final class TableReaderTest extends TestCase
{
    private const string EXAMPLE = __DIR__.'/../../../examples/lanterns.yaml';

    public function testReadsTheExample(): void
    {
        $catalogue = new TableReader()->load(self::EXAMPLE);
        $reaction = $catalogue->get('reaction');
        $wandering = $catalogue->get('wandering');
        $armor = $catalogue->get('armor');
        $advancement = $catalogue->get('advancement');

        self::assertCount(4, $catalogue);
        self::assertSame(['Réaction', 'Comment un inconnu accueille le groupe.', 'social', 'p. 12', 'lanterns', '2d6'], [$reaction->name('fr'), $reaction->description?->in('fr'), $reaction->category, $reaction->page, $reaction->system, $reaction->die]);
        self::assertSame(['Lanterns Quick Reference', Licence::Cc0, 'The Lanterns writers'], [$reaction->provenance->title, $reaction->provenance->licence, $reaction->provenance->author]);
        self::assertSame('Serviable', $reaction->lookup(12)->display('attitude', 'fr'));
        self::assertSame('Hostile', $reaction->lookup(0)->display('attitude'));
        self::assertSame('The lantern ghost', $wandering->lookup(100)->display('encounter'));
        self::assertSame(['1d100', '3d6', '1'], [$wandering->die, $wandering->lookup(1)->cell('number')->notation(), $wandering->lookup(80)->cell('number')->notation()]);
        self::assertSame(ColumnKind::Dice, $wandering->column('number')->kind);
        self::assertFalse($wandering->lookup(99)->has('number'));
        self::assertSame([Lookup::Key, 400, 'Chemise de mailles'], [$armor->lookup, $armor->row('plate')->cell('cost')->toInt(), $armor->row('chain')->display('name', 'fr')]);
        self::assertNull($armor->die);
        self::assertSame([Lookup::Range, null, 3], [$advancement->lookup, $advancement->die, $advancement->lookup(7)->cell('bonus')->toInt()]);
        self::assertNull($advancement->description);
    }

    public function testReadsJsonAsYaml(): void
    {
        $reader = new TableReader();
        $yaml = $reader->decode((string) file_get_contents(self::EXAMPLE), Syntax::Yaml);
        $json = $reader->parse(json_encode($yaml, \JSON_THROW_ON_ERROR), Syntax::Json);

        self::assertEquals($reader->catalogue($yaml), $json);
    }

    public function testLoadsAJsonFile(): void
    {
        $directory = sys_get_temp_dir().'/reference-tables-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $path = $directory.'/tables.JSON';
        file_put_contents($path, json_encode(self::minimal(), \JSON_THROW_ON_ERROR));

        try {
            self::assertTrue(new TableReader()->load($path)->has('t'));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function testKnowsTheSyntaxOfAFile(): void
    {
        self::assertSame(Syntax::Yaml, Syntax::ofPath('a.yml'));
        self::assertSame(Syntax::Yaml, Syntax::ofPath('a/b.YAML'));
        self::assertSame(Syntax::Json, Syntax::ofPath('a.json'));
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The file "tables.txt" is neither .yaml, .yml nor .json.');

        Syntax::ofPath('/somewhere/tables.txt');
    }

    public function testRefusesAFileItCannotRead(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The file "missing.yaml" cannot be read.');

        new TableReader()->load(__DIR__.'/missing.yaml');
    }

    public function testRefusesADirectory(): void
    {
        $directory = sys_get_temp_dir().'/reference-tables-'.bin2hex(random_bytes(4)).'.yaml';
        mkdir($directory);

        try {
            $this->expectExceptionMessageIsOrContains('cannot be read.');
            new TableReader()->load($directory);
        } finally {
            rmdir($directory);
        }
    }

    public function testRefusesAFileItMayNotRead(): void
    {
        $path = sys_get_temp_dir().'/reference-tables-'.bin2hex(random_bytes(4)).'.yaml';
        file_put_contents($path, 'format: x');
        chmod($path, 0o200);

        try {
            if (is_readable($path)) {
                self::markTestSkipped('The tests run with the right to read any file.');
            }

            $this->expectExceptionMessageIsOrContains('cannot be read.');
            new TableReader()->load($path);
        } finally {
            unlink($path);
        }
    }

    public function testReadsATableAtItsLimitOfColumns(): void
    {
        $document = self::minimal();
        $document['tables'][0]['columns'] = array_map(static fn (int $index): array => ['key' => 'c'.$index, 'names' => 'C'], range(1, 32));

        self::assertCount(32, new TableReader()->catalogue($document)->get('t')->columns);
    }

    public function testRefusesAFileLongerThanItsBound(): void
    {
        $size = (int) filesize(self::EXAMPLE);

        self::assertCount(4, new TableReader($size)->load(self::EXAMPLE));
        $this->expectExceptionMessageIsOrContains(\sprintf('The file "lanterns.yaml" is longer than %d bytes.', $size - 1));

        new TableReader($size - 1)->load(self::EXAMPLE);
    }

    public function testRefusesATextOneByteTooLong(): void
    {
        self::assertSame(['a' => 1], new TableReader(7)->decode('{"a":1}', Syntax::Json));
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The document is longer than 6 bytes.');

        new TableReader(6)->decode('{"a":1}', Syntax::Json);
    }

    /**
     * @return iterable<string, array{string, Syntax, string}>
     */
    public static function undecodable(): iterable
    {
        yield 'invalid YAML' => ["a: [\n", Syntax::Yaml, 'The document is not valid YAML:'];
        yield 'invalid JSON' => ['{', Syntax::Json, 'The document is not valid JSON:'];
        yield 'JSON too deep' => [str_repeat('[', 64).str_repeat(']', 64), Syntax::Json, 'The document is not valid JSON:'];
        yield 'a YAML object' => ['a: !php/object O:8:"stdClass":0:{}', Syntax::Yaml, 'The document is not valid YAML:'];
        yield 'a scalar' => ['12', Syntax::Yaml, 'The document is not a mapping.'];
    }

    #[DataProvider('undecodable')]
    public function testRefusesAText(string $text, Syntax $syntax, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TableReader()->decode($text, $syntax);
    }

    public function testAcceptsJsonAtItsDepth(): void
    {
        $nested = new TableReader()->decode(str_repeat('[', 63).str_repeat(']', 63), Syntax::Json);

        self::assertCount(1, $nested);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function refused(): iterable
    {
        $minimal = self::minimal();
        $table = $minimal['tables'][0];
        $with = static fn (array $changes): array => ['tables' => [array_replace($table, $changes)]] + $minimal;

        yield 'no format' => [array_diff_key($minimal, ['format' => true]), 'format: the document must name the format "tilttek-reference-tables/1".'];
        yield 'another format' => [['format' => 'tilttek-reference-tables/2'] + $minimal, 'format: the document must name the format'];
        yield 'a list' => [[1, 2], 'A mapping of names to values is expected.'];
        yield 'an unknown field' => [['extra' => 1] + $minimal, '"extra" is not one of format, locale, system, source, tables.'];
        yield 'no tables' => [['tables' => []] + $minimal, 'tables: a list of 1 to 2000 items is expected.'];
        yield 'tables as a mapping' => [['tables' => ['a' => $table]] + $minimal, 'tables: a list of 1 to 2000 items is expected.'];
        yield 'tables as a text' => [['tables' => 'all'] + $minimal, 'tables: a list of 1 to 2000 items is expected.'];
        yield 'a table twice' => [['tables' => [$table, $table]] + $minimal, 'tables: the catalogue has the table "t" twice.'];
        yield 'a table as a text' => [['tables' => ['t']] + $minimal, 'tables[0]: a mapping of names to values is expected.'];
        yield 'an unknown table field' => [$with(['colour' => 'red']), 'tables[0]: "colour" is not one of key, names, description'];
        yield 'no table key' => [['tables' => [array_diff_key($table, ['key' => true])]] + $minimal, 'tables[0].key: a text is expected.'];
        yield 'a table key as a list' => [$with(['key' => ['t']]), 'tables[0].key: a text is expected.'];
        yield 'a bad table key' => [$with(['key' => 'T']), 'tables[0]: the key of a table "T" must be'];
        yield 'no names' => [['tables' => [array_diff_key($table, ['names' => true])]] + $minimal, 'tables[0].names: a text, or texts by locale, is expected.'];
        yield 'names as a list' => [$with(['names' => ['T']]), 'tables[0].names: a text, or texts by locale, is expected.'];
        yield 'names as a number' => [$with(['names' => 12]), 'tables[0].names: a text, or texts by locale, is expected.'];
        yield 'a name as a number' => [$with(['names' => ['en' => 12]]), 'tables[0].names.en: a text is expected.'];
        yield 'a name in no locale' => [$with(['names' => ['english' => 'T']]), 'tables[0].names: "english" is not a locale'];
        yield 'an empty description' => [$with(['description' => []]), 'tables[0].description: a text, or texts by locale, is expected.'];
        yield 'an unknown lookup' => [$with(['lookup' => 'index']), 'tables[0].lookup: the lookup is "key" or "range".'];
        yield 'a die that cannot be read' => [$with(['die' => '1d']), 'tables[0].die: the dice cell "1d" cannot be read'];
        yield 'a die of two instructions' => [$with(['die' => '1d6, 1d4']), 'tables[0]: the die "1d6, 1d4" of the table "t" must be one instruction.'];
        yield 'a bad page' => [$with(['page' => "a\nb"]), 'tables[0]: the page of the table "t" must be one line'];
        yield 'no column' => [$with(['columns' => []]), 'tables[0].columns: a list of 1 to 32 items is expected.'];
        yield 'too many columns' => [$with(['columns' => array_map(static fn (int $index): array => ['key' => 'c'.$index, 'names' => 'C'], range(1, 33))]), 'tables[0].columns: a list of 1 to 32 items is expected.'];
        yield 'an unknown column field' => [$with(['columns' => [['key' => 'a', 'names' => 'A', 'width' => 3]]]), 'tables[0].columns[0]: "width" is not one of key, names, kind.'];
        yield 'an unknown kind' => [$with(['columns' => [['key' => 'a', 'names' => 'A', 'kind' => 'date']]]), 'tables[0].columns[0].kind: the kind is "text", "number" or "dice".'];
        yield 'a bad column key' => [$with(['columns' => [['key' => 'range', 'names' => 'A']]]), 'tables[0].columns[0]: a column cannot be named "range"'];
        yield 'no row' => [$with(['rows' => []]), 'tables[0].rows: a list of 1 to 1000 items is expected.'];
        yield 'too many rows' => [$with(['rows' => array_map(static fn (int $value): array => ['range' => (string) $value], range(1, 1001))]), 'tables[0].rows: a list of 1 to 1000 items is expected.'];
        yield 'a cell of no column' => [$with(['rows' => [['range' => '1', 'other' => 'x']]]), 'tables[0].rows[0]: "other" is not one of key, range, result.'];
        yield 'a bad range' => [$with(['rows' => [['range' => '5-3']]]), 'tables[0].rows[0].range: the range 5–3 runs backwards.'];
        yield 'a range as a list' => [$with(['rows' => [['range' => [1, 3]]]]), 'tables[0].rows[0].range: a text is expected.'];
        yield 'a row with both' => [$with(['rows' => [['range' => '1', 'key' => 'a']]]), 'tables[0].rows[0]: a row has a key or a range, not both nor neither.'];
        yield 'a keyed row by range' => [$with(['rows' => [['key' => 'a']]]), 'tables[0]: the row "a" of the table "t" needs a range'];
        yield 'overlapping rows' => [$with(['rows' => [['range' => '1-3'], ['range' => '3-4']]]), 'tables[0]: the rows "1–3" and "3–4" of the table "t" overlap.'];
        yield 'a text cell as a list' => [$with(['rows' => [['range' => '1', 'result' => ['a']]]]), 'tables[0].rows[0].result: a text, or texts by locale, is expected.'];
        yield 'an empty text cell' => [$with(['rows' => [['range' => '1', 'result' => ' ']]]), 'tables[0].rows[0].result: the text in "en" is required.'];
        yield 'a number cell as a text' => [self::withColumn('number', ['range' => '1', 'value' => '3']), 'tables[0].rows[0].value: a whole number is expected.'];
        yield 'a number cell too large' => [self::withColumn('number', ['range' => '1', 'value' => 2_000_000_000]), 'tables[0].rows[0].value: a number cell holds a value from'];
        yield 'a dice cell as a list' => [self::withColumn('dice', ['range' => '1', 'value' => ['1d6']]), 'tables[0].rows[0].value: a text is expected.'];
        yield 'a dice cell that cannot be read' => [self::withColumn('dice', ['range' => '1', 'value' => '1d']), 'tables[0].rows[0].value: the dice cell "1d" cannot be read'];
        yield 'no source' => [array_diff_key($minimal, ['source' => true]), 'tables[0].source: the table has no source, and the document none for it.'];
        yield 'a source as a text' => [['source' => 'SRD'] + $minimal, 'source: a mapping of names to values is expected.'];
        yield 'an unknown source field' => [['source' => ['title' => 'T', 'licence' => 'owned', 'year' => 2025]] + $minimal, 'source: "year" is not one of title, licence, attribution, author, url.'];
        yield 'no licence' => [['source' => ['title' => 'T']] + $minimal, 'source.licence: a text is expected.'];
        yield 'an unknown licence' => [['source' => ['title' => 'T', 'licence' => 'GPL-3.0']] + $minimal, 'source.licence: the licence is one of CC-BY-4.0, CC-BY-3.0, CC-BY-SA-4.0, CC0-1.0, ORC, owned.'];
        yield 'no attribution where it is asked' => [['source' => ['title' => 'T', 'licence' => 'CC-BY-4.0']] + $minimal, 'source: the attribution of the source "T" is required.'];
        yield 'a bad address' => [['source' => ['title' => 'T', 'licence' => 'owned', 'url' => 'ftp://x']] + $minimal, 'source: the address of the source "T" is not an absolute http or https address'];
        yield 'a bad table source' => [$with(['source' => ['title' => 'T', 'licence' => 'none']]), 'tables[0].source.licence: the licence is one of'];
        yield 'an empty row' => [$with(['rows' => [[]]]), 'tables[0].rows[0]: a row has a key or a range, not both nor neither.'];
        yield 'a name in a numbered locale' => [$with(['names' => [5 => 'T']]), 'tables[0].names: "5" is not a locale'];
        yield 'a bad locale' => [['locale' => 'english'] + $minimal, 'tables[0].columns[0].names: "english" is not a locale'];
        yield 'a bad system' => [['system' => 'Lanterns'] + $minimal, 'tables[0]: the key of the system of the table "t" "Lanterns" must be'];
    }

    /**
     * @param array<mixed> $document
     */
    #[DataProvider('refused')]
    public function testRefusesADocument(array $document, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TableReader()->catalogue($document);
    }

    public function testGivesTheRefusalItReplaces(): void
    {
        try {
            new TableReader()->catalogue(['tables' => [['key' => 'T'] + self::minimal()['tables'][0]]] + self::minimal());
            self::fail('A bad key is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertNotNull($invalidDocument->getPrevious());
            self::assertSame(0, $invalidDocument->getCode());
            self::assertStringStartsWith('tables[0]: the key', $invalidDocument->getMessage());
        }
    }

    public function testReadsTheDefaultsAndTheOverrides(): void
    {
        $document = self::minimal();
        $document['locale'] = 'fr';
        $document['system'] = 'lanterns';
        $document['tables'][] = ['key' => 'u', 'names' => 'U', 'system' => 'other', 'lookup' => 'key', 'category' => 'misc', 'page' => 12, 'source' => ['title' => 'Own', 'licence' => 'owned', 'attribution' => 'By us', 'author' => 'Us', 'url' => 'https://example.org/'], 'columns' => [['key' => 'value', 'names' => 'Valeur', 'kind' => 'number']], 'rows' => [['key' => 'a', 'value' => 3], ['key' => 7]]];
        $catalogue = new TableReader()->catalogue($document);
        $t = $catalogue->get('t');
        $u = $catalogue->get('u');

        self::assertSame(['T', 'lanterns', null, null], [$t->names->in('fr'), $t->system, $t->category, $t->page]);
        self::assertSame(['fr'], $t->names->locales());
        self::assertSame(['other', 'misc', '12', 'Own', 'By us', 'Us', 'https://example.org/'], [$u->system, $u->category, $u->page, $u->provenance->title, $u->provenance->attribution, $u->provenance->author, $u->provenance->url?->toString()]);
        self::assertSame('', $t->provenance->attribution);
        self::assertNull($t->provenance->author);
        self::assertNull($t->provenance->url);
        self::assertSame(3, $u->row('a')->cell('value')->toInt());
        self::assertSame('7', $u->row('7')->label());
        self::assertSame('Valeur', $u->column('value')->name('fr'));
    }

    public function testReadsTextCellsWrittenAsNumbers(): void
    {
        $document = self::minimal();
        $document['tables'][0]['rows'] = [['range' => '1', 'result' => 12], ['range' => 2, 'result' => ['en' => 'Two', 'fr' => 'Deux']]];
        $table = new TableReader()->catalogue($document)->get('t');

        self::assertSame('12', $table->lookup(1)->display('result'));
        self::assertSame('Deux', $table->lookup(2)->display('result', 'fr'));
    }

    public function testReadsTheEndOfAD100OnlyOnAD100(): void
    {
        $document = self::minimal();
        $document['tables'][0]['die'] = 'd%';
        $document['tables'][0]['rows'] = [['range' => '01-50'], ['range' => '51-00']];
        $percentile = new TableReader()->catalogue($document)->get('t');
        $document['tables'][0]['die'] = '1d20';
        $document['tables'][0]['rows'] = [['range' => '00'], ['range' => '1-20']];
        $d20 = new TableReader()->catalogue($document)->get('t');
        unset($document['tables'][0]['die']);
        $none = new TableReader()->catalogue($document)->get('t');

        self::assertSame('51–100', $percentile->lookup(100)->label());
        self::assertSame('0', $d20->lookup(0)->label());
        self::assertSame('0', $none->lookup(0)->label());
    }

    /**
     * @return array{format: string, source: array{title: string, licence: string}, tables: list<array<string, mixed>>}
     */
    private static function minimal(): array
    {
        return [
            'format' => TableReader::FORMAT,
            'source' => ['title' => 'Test', 'licence' => 'owned'],
            'tables' => [['key' => 't', 'names' => 'T', 'columns' => [['key' => 'result', 'names' => 'Result']], 'rows' => [['range' => '1']]]],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<mixed>
     */
    private static function withColumn(string $kind, array $row): array
    {
        $document = self::minimal();
        $document['tables'][0]['columns'] = [['key' => 'value', 'names' => 'Value', 'kind' => $kind]];
        $document['tables'][0]['rows'] = [$row];

        return $document;
    }
}
