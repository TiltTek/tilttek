<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Import\Imported;
use Tilttek\Generator\Import\Perchance\Branch;
use Tilttek\Generator\Import\Perchance\Choice;
use Tilttek\Generator\Import\Perchance\Dice;
use Tilttek\Generator\Import\Perchance\Range;
use Tilttek\Generator\Import\Perchance\Reference;
use Tilttek\Generator\Import\Perchance\TableParser;
use Tilttek\Generator\Import\Perchance\Text;
use Tilttek\Generator\Import\PerchanceImporter;
use Tilttek\Generator\Import\TemplateText;
use Tilttek\Generator\Tests\Unit\Fixture\Documents;

#[CoversClass(PerchanceImporter::class)]
#[CoversClass(Imported::class)]
#[CoversClass(TemplateText::class)]
#[CoversClass(TableParser::class)]
#[CoversClass(Branch::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Dice::class)]
#[CoversClass(Range::class)]
#[CoversClass(Reference::class)]
#[CoversClass(Text::class)]
final class PerchanceImporterTest extends TestCase
{
    public function testSectionsBecomeListsAndGenerators(): void
    {
        $imported = new PerchanceImporter()->import(<<<'TXT'
            title
              Beasts

            animal
              dog
              cat ^2
              owl ^0.5 // a comment
              https://example.org/bat

            Coat Colour
            	red
            	blue

            output
              A [coat colour] [animal].
            TXT);

        self::assertSame('output', $imported->start);
        self::assertSame([
            'lists' => [
                'animal' => ['entries' => [['text' => 'dog', 'weight' => 2], ['text' => 'cat', 'weight' => 4], 'owl', ['text' => 'https://example.org/bat', 'weight' => 2]]],
                'coat-colour' => ['entries' => ['red', 'blue']],
            ],
            'generators' => [
                'output' => ['templates' => ["A {{ pick('coat-colour') }} {{ pick('animal') }}."]],
            ],
        ], $imported->document);
        self::assertStringStartsWith('A ', Documents::text(new Generator($imported->catalog())->draw('output')->first()));
    }

    public function testTheFirstSectionIsDrawnFirstWithoutOutput(): void
    {
        $imported = new PerchanceImporter('t.', new Attribution(licence: 'CC-BY-4.0'), 'fr')->import("first\n  one\n  two ^3\nsecond\n  [first]\n");

        self::assertSame('t.first', $imported->start);
        self::assertSame([
            't.first' => ['locale' => 'fr', 'templates' => ['one', ['template' => 'two', 'weight' => 3]], 'licence' => 'CC-BY-4.0'],
            't.second' => ['locale' => 'fr', 'templates' => ["{{ generate('t.first') }}"], 'licence' => 'CC-BY-4.0'],
        ], $imported->document['generators'] ?? null);
    }

    public function testChoicesRangesDiceAndSelections(): void
    {
        $imported = new PerchanceImporter()->import(<<<'TXT'
            colour
              red
              blue
            output
              {big|small^3} {1-6} 2d6+1 d20 {a|{b|c}}
              [colour.selectUnique(2)]; [colour.selectMany(3)]
              x2d6 and 3d5000 stay text
            TXT);

        self::assertSame([
            "{% set choice_1 = between(1, 4) %}{% if choice_1 <= 1 %}big{% else %}small{% endif %} {{ between(1, 6) }} {{ roll('2d6+1') }} {{ roll('d20') }} {% set choice_2 = between(1, 2) %}{% if choice_2 <= 1 %}a{% else %}{% set choice_3 = between(1, 2) %}{% if choice_3 <= 1 %}b{% else %}c{% endif %}{% endif %}",
            "{{ pick('colour', 2)|join(', ') }}; {{ [pick('colour'), pick('colour'), pick('colour')]|join(', ') }}",
            'x2d6 and 3d5000 stay text',
        ], Documents::templates($imported, 'output'));

        $generator = new Generator($imported->catalog());
        $results = Documents::texts($generator->draw('output', new Draw(40))->results);
        self::assertContains('x2d6 and 3d5000 stay text', $results);
        self::assertTrue(array_any($results, static fn (string $result): bool => 1 === preg_match('/^(big|small) [1-6] \d+ \d+ [abc]$/', $result)));
        self::assertTrue(array_any($results, static fn (string $result): bool => \in_array($result, ['red, blue; red, red, red', 'blue, red; blue, blue, blue'], true) || 1 === preg_match('/^(red|blue), (red|blue); /', $result)));
    }

    public function testAChoiceWithThreePartsCountsItsRunningWeights(): void
    {
        $imported = new PerchanceImporter()->import("output\n  {a^0.5|b|c^1.25}\n");

        self::assertSame(['{% set choice_1 = between(1, 11) %}{% if choice_1 <= 2 %}a{% elseif choice_1 <= 6 %}b{% else %}c{% endif %}'], Documents::templates($imported, 'output'));
    }

    public function testWeightsKeepThreeDigitsAfterThePoint(): void
    {
        $imported = new PerchanceImporter()->import("list\n  a ^0.0004\n  b ^2\noutput\n  [list]\n");

        self::assertSame(['a', ['text' => 'b', 'weight' => 2000]], Documents::entries($imported, 'list'));
    }

    public function testABackslashKeepsTheNextCharacter(): void
    {
        $imported = new PerchanceImporter()->import("output\n  \\[x\\] \\{a\\|b\\} 2\\d6 a\\^2 {c\\|d|e\\^2}\n");

        $results = array_values(array_unique(Documents::texts(new Generator($imported->catalog())->draw('output', new Draw(30))->results)));
        sort($results);

        self::assertSame(['[x] {a|b} 2d6 a^2 c|d', '[x] {a|b} 2d6 a^2 e^2'], $results);
        self::assertStringStartsWith("{{ '[x] {a|b} 2d6 a^2 ' }}", Documents::text(Documents::templates($imported, 'output')[0]));
    }

    public function testTheBlocksOfMarkdownAreRead(): void
    {
        $markdown = "# Tables\n\nSome text.\n\n```perhaps\nanimal\n  owl\n```\n\nMore.\n\n~~~ Perchance\noutput\n  [animal]\n~~~\n```\nignored\n  x\n```\n";

        $document = new PerchanceImporter()->import($markdown)->document;

        self::assertSame(['animal'], array_keys($document['lists'] ?? []));
        self::assertSame(['output'], array_keys($document['generators'] ?? []));
    }

    public function testCarriageReturnsEndLinesToo(): void
    {
        self::assertSame(['a', 'b'], Documents::entries(new PerchanceImporter()->import("output\r\n  [l]\r\nl\r  a\r\n  b"), 'l'));
    }

    public function testAnEmptySectionCannotBeReferenced(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('perchance > output', 'the section "empty" has no entry'));

        new PerchanceImporter()->import("empty\noutput\n  [empty]\n");
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'nothing' => ["// only a comment\n", 'perchance: the tables have no section'];
        yield 'an empty output' => ["output\nother\n  a\n", 'perchance > output: the section drawn first has no entry'];
        yield 'an entry outside a section' => ["  a\n", 'perchance > line 1: an entry is under a section'];
        yield 'one space' => ["output\n a\n", 'perchance > line 2: an entry is indented by a tab or two spaces'];
        yield 'a bad name' => ["out/put\n  a\n", 'perchance > line 1: "out/put" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'a bad weight' => ["output\n  a ^x\n", 'perchance > line 2: "x" is not a weight: a positive number of at most 1000000'];
        yield 'a zero weight' => ["output\n  a ^0\n", 'perchance > line 2: "0" is not a weight: a positive number of at most 1000000'];
        yield 'too heavy a weight' => ["output\n  a ^1000000.5\n", 'perchance > line 2: "1000000.5" is not a weight: a positive number of at most 1000000'];
        yield 'a weight of too many digits' => ["output\n  a ^27777777777777\n", 'perchance > line 2: "27777777777777" is not a weight: a positive number of at most 1000000'];
        yield 'an unknown section' => ["output\n  [nope]\n", 'perchance > output: the section "nope" does not exist'];
        yield 'an open bracket' => ["output\n  [a\n", 'perchance > line 2: "[" is not closed'];
        yield 'an open brace' => ["output\n  {a|b\n", 'perchance > line 2: "{" is not closed'];
        yield 'a choice of one' => ["output\n  {a}\n", 'perchance > line 2: a choice has at least two parts: {a|b}'];
        yield 'an empty range' => ["output\n  {6-1}\n", 'perchance > line 2: the range {6-1} is empty'];
        yield 'a method' => ["output\n  [a.joinItems(1)]\n", 'perchance > line 2: "[a.joinItems(1)]" is not supported: a reference is [name], [name.selectUnique(n)] or [name.selectMany(n)]'];
        yield 'an assignment' => ["output\n  [x = a]\n", 'perchance > line 2: "x-=-a" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'too many selected' => ["a\n  b\noutput\n  [a.selectMany(101)]\n", 'perchance > line 4: a selection takes from 1 to 100 entries'];
        yield 'none selected' => ["a\n  b\noutput\n  [a.selectUnique(0)]\n", 'perchance > line 4: a selection takes from 1 to 100 entries'];
        yield 'not UTF-8' => ["output\n  \xC3\n", 'perchance: the tables are written in UTF-8'];
        yield 'too deep' => ["output\n  ".str_repeat('{a|', 11).str_repeat('}', 11)."\n", 'perchance > line 2: choices nest at most 10 deep'];
        yield 'a weight that starts with a letter' => ["output\n  a ^x12\n", 'perchance > line 2: "x12" is not a weight: a positive number of at most 1000000'];
        yield 'a weight that starts with a point' => ["output\n  a ^.5\n", 'perchance > line 2: ".5" is not a weight: a positive number of at most 1000000'];
        yield 'a weight that ends with a letter' => ["output\n  a ^12x\n", 'perchance > line 2: "12x" is not a weight: a positive number of at most 1000000'];
        yield 'a range that starts with a letter' => ["output\n  {x1-6}\n", 'perchance > line 2: a choice has at least two parts: {a|b}'];
        yield 'a range that ends with a letter' => ["output\n  {1-6x}\n", 'perchance > line 2: a choice has at least two parts: {a|b}'];
        yield 'a fence that does not start its line' => ["foo ```perhaps\nanimal\n  owl\n", 'perchance > line 1: "foo-```perhaps" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'a fence followed by a word' => ["```perhaps x\nanimal\n  owl\n", 'perchance > line 1: "```perhaps-x" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'a section name in capitals that is not a name' => ["\u{C9}lan\n  a\n", 'perchance > line 1: "élan" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'a reference in capitals that is not a name' => ["output\n  [\u{C9}lan]\n", 'perchance > line 2: "élan" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
    }

    #[DataProvider('refusals')]
    public function testARefusalSaysWhere(string $text, string $message): void
    {
        $this->expectExceptionObject(new InvalidDefinition($message));

        new PerchanceImporter()->import($text);
    }

    public function testTheTablesAreBounded(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('perchance', 'the tables hold at most 1048576 bytes'));

        new PerchanceImporter()->import("output\n  ".str_repeat('a', PerchanceImporter::MAX_BYTES));
    }

    public function testASectionIsBounded(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('perchance > line 4098', 'a section has at most 4096 entries'));

        new PerchanceImporter()->import("output\n".str_repeat("  a\n", PerchanceImporter::MAX_SECTIONS + 1));
    }

    public function testTheSectionsAreBounded(): void
    {
        // The title is not a section.
        $text = "title\n  T\n".implode('', array_map(static fn (int $index): string => 's'.$index."\n  a\n", range(1, PerchanceImporter::MAX_SECTIONS)));
        self::assertSame('s1', new PerchanceImporter()->import($text)->start);

        $text = implode('', array_map(static fn (int $index): string => 's'.$index."\n  a\n", range(0, PerchanceImporter::MAX_SECTIONS)));

        $this->expectExceptionObject(InvalidDefinition::at('perchance > line 8193', 'the tables have at most 4096 sections'));

        new PerchanceImporter()->import($text);
    }

    public function testASectionMadeOfDigitsDrawnFirstIsAGenerator(): void
    {
        $imported = new PerchanceImporter()->import("1\n  x\n");

        self::assertSame('1', $imported->start);
        self::assertSame(['x'], Documents::templates($imported, '1'));
        self::assertArrayNotHasKey('lists', $imported->document);
    }

    public function testThePrefixIsPutBeforeTheNamesOfTheLists(): void
    {
        $imported = new PerchanceImporter('t.')->import("animal\n  owl\noutput\n  [animal]\n");

        self::assertSame(['t.animal'], array_keys($imported->document['lists'] ?? []));
        self::assertSame(["{{ pick('t.animal') }}"], Documents::templates($imported, 't.output'));
        self::assertSame('owl', new Generator($imported->catalog())->draw('t.output')->first());
    }

    public function testThePrefixMakesTheNames(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('perchance > line 1', '"a" is not a name: letters, digits, "_" and "-", in parts joined by dots'));

        new PerchanceImporter('bad prefix.')->import("a\n  x\n");
    }

    public function testALineOfSpacesIsEmpty(): void
    {
        self::assertSame(['a', 'b'], Documents::templates(new PerchanceImporter()->import("l\n  a\n   \n  b\n"), 'l'));
    }

    public function testASectionNameKeepsItsInnerSpacesAsOneHyphen(): void
    {
        self::assertSame('a-b', new PerchanceImporter()->import("a  b\n  x\n")->start);
        self::assertSame('a-b', new PerchanceImporter()->import("A \t B\n  x\n")->start);
    }

    public function testASectionNamedAgainKeepsItsEntries(): void
    {
        self::assertSame(['x', 'z'], Documents::templates(new PerchanceImporter()->import("a\n  x\nb\n  y\na\n  z\n"), 'a'));
    }

    public function testASectionNamedAgainOrTheTitleDoesNotCountAtTheBound(): void
    {
        $sections = implode('', array_map(static fn (int $index): string => 's'.$index."\n  a\n", range(1, PerchanceImporter::MAX_SECTIONS)));

        self::assertSame(['a', 'b'], Documents::templates(new PerchanceImporter()->import($sections."s1\n  b\n"), 's1'));
        self::assertSame('s1', new PerchanceImporter()->import($sections."title\n  T\n")->start);
    }

    public function testOnlyLinesThatStartWithAFenceAreFences(): void
    {
        self::assertSame(['a ```', 'b'], Documents::templates(new PerchanceImporter()->import("```perhaps\noutput\n  a ```\n  b\n```\n"), 'output'));
        self::assertSame(['a', '``` x', 'b'], Documents::templates(new PerchanceImporter()->import("```perhaps\noutput\n  a\n  ``` x\n  b\n```\n"), 'output'));
        self::assertSame(['a'], Documents::templates(new PerchanceImporter()->import("  ~~~PERCHANCE  \noutput\n  a\n  ~~~  \nignored\n  b\n"), 'output'));
    }

    public function testAReferenceIsTrimmed(): void
    {
        $imported = new PerchanceImporter()->import("a\n  x\noutput\n  [ a ] [ a . selectUnique( 2 ) ] [\ta.selectMany(2)\t]\n");

        self::assertSame(["{{ pick('a') }} {{ pick('a', 2)|join(', ') }} {{ [pick('a'), pick('a')]|join(', ') }}"], Documents::templates($imported, 'output'));
    }

    public function testASelectionTakesFromOneToAHundredEntries(): void
    {
        $imported = new PerchanceImporter()->import("a\n  x\noutput\n  [a.selectUnique(1)] [a.selectMany(100)]\n");

        $template = Documents::text(Documents::templates($imported, 'output')[0]);
        self::assertStringStartsWith("{{ pick('a', 1)|join(', ') }} {{ [pick('a'), ", $template);
        self::assertSame(100, substr_count($template, "pick('a')"));
    }

    public function testTheHeaviestWeightIsAMillion(): void
    {
        self::assertSame([['template' => 'a', 'weight' => 1_000_000], 'b'], Documents::templates(new PerchanceImporter()->import("output\n  a ^1000000\n  b\n"), 'output'));
    }

    public function testWeightsAreRoundedToThreeDigits(): void
    {
        $imported = new PerchanceImporter()->import("down\n  a ^1.2344\n  b\nup\n  a ^1.2346\n  b\noutput\n  [down][up]\n");

        self::assertSame([['text' => 'a', 'weight' => 617], ['text' => 'b', 'weight' => 500]], Documents::entries($imported, 'down'));
        self::assertSame([['text' => 'a', 'weight' => 247], ['text' => 'b', 'weight' => 200]], Documents::entries($imported, 'up'));
    }

    public function testTenChoicesMayNest(): void
    {
        $imported = new PerchanceImporter()->import("output\n  ".str_repeat('{a|', 10).'b'.str_repeat('}', 10)."\n");

        self::assertContains(new Generator($imported->catalog())->draw('output')->first(), ['a', 'b']);
    }

    public function testATrailingBackslashIsKept(): void
    {
        self::assertSame('a\\', new Generator(new PerchanceImporter()->import("output\n  a\\\n")->catalog())->draw('output')->first());
    }

    public function testARangeMayHaveOneNumber(): void
    {
        self::assertSame(['{{ between(3, 3) }}'], Documents::templates(new PerchanceImporter()->import("output\n  {3-3}\n"), 'output'));
        self::assertSame(['{{ between(0, 7) }}'], Documents::templates(new PerchanceImporter()->import("output\n  { 00 - 7 }\n"), 'output'));
    }

    public function testTheTablesMayHoldTheBound(): void
    {
        $text = "output\n  ".str_repeat('a', PerchanceImporter::MAX_BYTES - 9);
        self::assertSame(PerchanceImporter::MAX_BYTES, \strlen($text));

        self::assertSame('output', new PerchanceImporter()->import($text)->start);
    }

    public function testBracesAloneAreWrittenAsStrings(): void
    {
        self::assertSame("{{ 'a{b' }}", TemplateText::literal('a{b'));
        self::assertSame("{{ 'a}b' }}", TemplateText::literal('a}b'));
        self::assertSame('ab', TemplateText::literal('ab'));
        self::assertSame('', TemplateText::literal(''));
    }
}
