<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\GenerateField;
use Tilttek\Generator\Definition\GraphBody;
use Tilttek\Generator\Definition\GridBody;
use Tilttek\Generator\Definition\MapLayout;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Definition\ObjectBody;
use Tilttek\Generator\Definition\StateValue;
use Tilttek\Generator\Definition\Template;
use Tilttek\Generator\Definition\TemplateField;
use Tilttek\Generator\Definition\TextBody;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\Reader;

#[CoversClass(Reader::class)]
final class ReaderTest extends TestCase
{
    public function testItReadsListsAndTheirEntries(): void
    {
        $catalog = new Reader()->catalog(['a.yaml' => ['lists' => ['name.elf.first' => [
            'Aelis',
            12,
            ['text' => 'épée', 'weight' => 3, 'gender' => 'f', 'forms' => ['x', 2], 'tags' => ['kind' => 'blade', 'era' => ['old', 1]]],
            ['text' => 'arc', 'tags' => ['kind' => ['a' => 'bow']]],
        ]]]]);

        $entries = $catalog->list('name.elf.first')->entries;
        self::assertEquals([
            new Entry('Aelis'),
            new Entry('12'),
            new Entry('épée', 3, ['gender' => 'f', 'forms' => ['x', '2']], ['kind' => ['blade'], 'era' => ['old', '1']]),
            new Entry('arc', 1, [], ['kind' => ['bow']]),
        ], $entries);
    }

    public function testItReadsOneListOrOneGeneratorByDocument(): void
    {
        $catalog = new Reader()->catalog([
            'generator.yaml' => ['generator' => 'item', 'templates' => "{{ pick('weapon') }}"],
            'list.yaml' => ['list' => 'weapon', 'entries' => ['épée']],
            'more.yaml' => ['lists' => ['tool' => ['marteau']]],
        ]);

        self::assertTrue($catalog->hasList('tool'));

        self::assertTrue($catalog->hasList('weapon'));
        self::assertTrue($catalog->hasGenerator('item'));
    }

    public function testItReadsTextGenerators(): void
    {
        $generator = new Reader()->catalog(['g' => ['generators' => ['item' => [
            'locale' => 'fr',
            'vars' => ['gender' => "{{ 'f' }}"],
            'templates' => ['a', ['template' => 'b', 'weight' => 4], ['template' => 'c']],
            'filters' => ['upper_first', 'elide'],
        ]]]])->generator('item');

        self::assertSame('fr', $generator->locale);
        self::assertSame(['gender' => "{{ 'f' }}"], $generator->vars);
        self::assertInstanceOf(TextBody::class, $generator->body);
        self::assertSame([['a', 1], ['b', 4], ['c', 1]], array_map(static fn (Template $template): array => [$template->source, $template->weight], $generator->body->templates));
        self::assertSame(['upper_first', 'elide'], $generator->body->filters);
    }

    public function testItReadsStructuredGenerators(): void
    {
        $body = new Reader()->catalog(['g' => ['generators' => ['inn' => ['fields' => [
            'name' => 'The {{ x }}',
            'keeper' => ['generate' => 'npc'],
            'staff' => ['generate' => 'npc', 'count' => 3],
            'cook' => ['generate' => 'npc', 'count' => 1],
        ]]]]])->generator('inn')->body;

        self::assertEquals(new ObjectBody(['name' => new TemplateField('The {{ x }}'), 'keeper' => new GenerateField('npc'), 'staff' => new GenerateField('npc', 3), 'cook' => new GenerateField('npc', 1)]), $body);
    }

    public function testItReadsMaps(): void
    {
        $catalog = new Reader()->catalog(['g' => ['generators' => [
            'village' => ['map' => ['layout' => 'square', 'width' => 3, 'height' => 2, 'cell' => 'x']],
            'region' => ['map' => ['layout' => 'hex', 'width' => 1, 'height' => 1, 'cell' => ['generate' => 'terrain']]],
            'dungeon' => ['map' => ['layout' => 'graph', 'nodes' => '1d4+2', 'extra_edges' => 2, 'cell' => 'room']],
            'cave' => ['map' => ['layout' => 'graph', 'nodes' => 1, 'cell' => 'room']],
        ]]]);

        self::assertEquals(new GridBody(MapLayout::Square, 3, 2, new TemplateField('x')), $catalog->generator('village')->body);
        self::assertEquals(new GridBody(MapLayout::Hex, 1, 1, new GenerateField('terrain')), $catalog->generator('region')->body);
        self::assertEquals(new GraphBody('1d4+2', 2, new TemplateField('room')), $catalog->generator('dungeon')->body);
        self::assertEquals(new GraphBody(1, 0, new TemplateField('room')), $catalog->generator('cave')->body);
    }

    public function testItReadsMarkovLists(): void
    {
        $catalog = new Reader()->catalog([
            'a' => ['lists' => [
                'plain' => ['entries' => ['x']],
                'default' => ['entries' => ['x'], 'markov' => true],
                'set' => ['entries' => ['x'], 'markov' => ['order' => 3, 'min_length' => 2, 'max_length' => 5, 'allow_entries' => true]],
                'half' => ['entries' => ['x'], 'markov' => ['order' => 1]],
                'lengths' => ['entries' => ['x'], 'markov' => ['min_length' => 4]],
            ]],
            'b' => ['list' => 'single', 'entries' => ['x'], 'markov' => true],
        ]);

        self::assertNull($catalog->list('plain')->markov);
        self::assertEquals(new MarkovChain(), $catalog->list('default')->markov);
        self::assertEquals(new MarkovChain(3, 2, 5, true), $catalog->list('set')->markov);
        self::assertEquals(new MarkovChain(1), $catalog->list('half')->markov);
        self::assertEquals(new MarkovChain(2, 4), $catalog->list('lengths')->markov);
        self::assertEquals(new MarkovChain(), $catalog->list('single')->markov);
        self::assertEquals([new Entry('x')], $catalog->list('set')->entries);
    }

    public function testItReadsTheStateOfAGenerator(): void
    {
        $generator = new Reader()->catalog(['a' => ['generators' => ['g' => [
            'templates' => '{{ state.season }}',
            'state' => ['season' => 'spring', 'day' => ['start' => '1', 'next' => '{{ state.day + 1 }}'], 'mood' => ['start' => 'calm']],
        ]]]])->generator('g');

        self::assertEquals(['season' => new StateValue('spring'), 'day' => new StateValue('1', '{{ state.day + 1 }}'), 'mood' => new StateValue('calm')], $generator->state);
        self::assertTrue($generator->hasState());
        self::assertFalse(new Reader()->catalog(['a' => ['generators' => ['g' => ['templates' => 'x']]]])->generator('g')->hasState());
    }

    public function testAnEmptyDocumentGivesAnEmptyCatalog(): void
    {
        self::assertSame([], new Reader()->catalog(['empty' => []])->lists());
        self::assertSame([], new Reader()->catalog([])->generators());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not a map' => [['a'], 'f: a map of names is expected'];
        yield 'not an array' => ['text', 'f: a map of names is expected'];
        yield 'unknown key of a single list' => [['list' => 'a', 'entries' => ['x'], 'tags' => []], 'f: unknown keys "tags"; the keys are "list", "entries", "markov"'];
        yield 'unknown top key' => [['things' => []], 'f: unknown keys "things"; the keys are "lists", "generators"'];
        yield 'lists not a map' => [['lists' => ['a']], 'f > lists: a map of names is expected'];
        yield 'generators not a map' => [['generators' => 'x'], 'f > generators: a map of names is expected'];
        yield 'bad list name' => [['lists' => ['a b' => ['x']]], 'f: "a b" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'list name with an empty part' => [['lists' => ['a..b' => ['x']]], 'f: "a..b" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'bad single list name' => [['list' => ['x'], 'entries' => ['x']], 'f: "array" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'missing entries' => [['list' => 'a'], 'f: a list is a sequence of at least one entry'];
        yield 'empty list' => [['lists' => ['a' => []]], 'f > lists > a: a list is a sequence of at least one entry'];
        yield 'list neither a sequence nor a list map' => [['lists' => ['a' => ['x' => 'y']]], 'f > lists > a: unknown keys "x"; the keys are "entries", "markov"'];
        yield 'list not an array' => [['lists' => ['a' => 'x']], 'f > lists > a: a list is a sequence of at least one entry'];
        yield 'entry not a map' => [['lists' => ['a' => [['x', 'y']]]], 'f > lists > a > 0: a map of names is expected'];
        yield 'entry without text' => [['lists' => ['a' => ['x', ['weight' => 2]]]], 'f > lists > a > 1: an entry has a text, and its weight is a whole number'];
        yield 'entry with a text that is not scalar' => [['lists' => ['a' => [['text' => ['x']]]]], 'f > lists > a > 0: an entry has a text, and its weight is a whole number'];
        yield 'entry with a weight that is not whole' => [['lists' => ['a' => [['text' => 'x', 'weight' => '2']]]], 'f > lists > a > 0: an entry has a text, and its weight is a whole number'];
        yield 'entry with a weight of zero' => [['lists' => ['a' => [['text' => 'x', 'weight' => 0]]]], 'f > lists > a > 0: x: the weight of an entry is a whole number of at least 1'];
        yield 'tags not a map' => [['lists' => ['a' => [['text' => 'x', 'tags' => ['y']]]]], 'f > lists > a > 0 > tags: a map of names is expected'];
        yield 'tag value not scalar' => [['lists' => ['a' => [['text' => 'x', 'tags' => ['k' => [['y']]]]]]], 'f > lists > a > 0 > tags > k: a field is a scalar or a sequence of scalars'];
        yield 'field not scalar' => [['lists' => ['a' => [['text' => 'x', 'f' => null]]]], 'f > lists > a > 0 > f: a field is a scalar or a sequence of scalars'];
        yield 'field list not scalar' => [['lists' => ['a' => [['text' => 'x', 'f' => [['y']]]]]], 'f > lists > a > 0 > f: a field is a scalar or a sequence of scalars'];
        yield 'generator not a map' => [['generators' => ['g' => ['x']]], 'f > generators > g: a map of names is expected'];
        yield 'bad generator name' => [['generators' => ['g!' => ['templates' => 'x']]], 'f: "g!" is not a name: letters, digits, "_" and "-", in parts joined by dots'];
        yield 'unknown generator key' => [['generators' => ['g' => ['templates' => 'x', 'kind' => 'y']]], 'f > generators > g: unknown keys "kind"; the keys are "locale", "state", "vars", "templates", "filters", "fields", "map"'];
        yield 'locale not a text' => [['generators' => ['g' => ['templates' => 'x', 'locale' => 1]]], 'f > generators > g: the locale is a text such as "fr" or "en_CA"'];
        yield 'vars not a map' => [['generators' => ['g' => ['templates' => 'x', 'vars' => ['a']]]], 'f > generators > g > vars: a map of names is expected'];
        yield 'var not a template' => [['generators' => ['g' => ['templates' => 'x', 'vars' => ['a' => 1]]]], 'f > generators > g > vars > a: a template is a text'];
        yield 'no body' => [['generators' => ['g' => ['locale' => 'fr']]], 'f > generators > g: a generator has exactly one of "templates", "fields" and "map"'];
        yield 'two bodies' => [['generators' => ['g' => ['templates' => 'x', 'fields' => ['a' => 'b']]]], 'f > generators > g: a generator has exactly one of "templates", "fields" and "map"'];
        yield 'filters without templates' => [['generators' => ['g' => ['fields' => ['a' => 'b'], 'filters' => []]]], 'f > generators > g: only a generator with "templates" has "filters"'];
        yield 'templates empty' => [['generators' => ['g' => ['templates' => []]]], 'f > generators > g > templates: templates are a template or a sequence of at least one'];
        yield 'templates not a sequence' => [['generators' => ['g' => ['templates' => ['a' => 'x']]]], 'f > generators > g > templates: templates are a template or a sequence of at least one'];
        yield 'templates not an array' => [['generators' => ['g' => ['templates' => 1]]], 'f > generators > g > templates: templates are a template or a sequence of at least one'];
        yield 'template not a text' => [['generators' => ['g' => ['templates' => [1]]]], 'f > generators > g > templates: a template is a text'];
        yield 'weighted template without a template' => [['generators' => ['g' => ['templates' => [['weight' => 2]]]]], 'f > generators > g > templates: a template is a text'];
        yield 'template weight not whole' => [['generators' => ['g' => ['templates' => [['template' => 'x', 'weight' => 'y']]]]], 'f > generators > g > templates: the weight of a template is a whole number of at least 1'];
        yield 'template weight zero' => [['generators' => ['g' => ['templates' => [['template' => 'x', 'weight' => 0]]]]], 'f > generators > g > templates: the weight of a template is a whole number of at least 1'];
        yield 'filters not a sequence' => [['generators' => ['g' => ['templates' => 'x', 'filters' => 'upper']]], 'f > generators > g > filters: filters are a sequence of filter names'];
        yield 'filters a map' => [['generators' => ['g' => ['templates' => 'x', 'filters' => ['a' => 'upper']]]], 'f > generators > g > filters: filters are a sequence of filter names'];
        yield 'filter not a text' => [['generators' => ['g' => ['templates' => 'x', 'filters' => [1]]]], 'f > generators > g > filters: a template is a text'];
        yield 'fields not a map' => [['generators' => ['g' => ['fields' => ['x']]]], 'f > generators > g > fields: a map of names is expected'];
        yield 'field neither' => [['generators' => ['g' => ['fields' => ['a' => 1]]]], 'f > generators > g > fields > a: a template is a text'];
        yield 'field without generate' => [['generators' => ['g' => ['fields' => ['a' => ['count' => 2]]]]], 'f > generators > g > fields > a: a field is a template, or "generate" with a generator name and an optional "count" of at least 1'];
        yield 'field count not whole' => [['generators' => ['g' => ['fields' => ['a' => ['generate' => 'x', 'count' => '2']]]]], 'f > generators > g > fields > a: a field is a template, or "generate" with a generator name and an optional "count" of at least 1'];
        yield 'field count zero' => [['generators' => ['g' => ['fields' => ['a' => ['generate' => 'x', 'count' => 0]]]]], 'f > generators > g > fields > a: a field is a template, or "generate" with a generator name and an optional "count" of at least 1'];
        yield 'map not a map' => [['generators' => ['g' => ['map' => 'x']]], 'f > generators > g > map: a map of names is expected'];
        yield 'map without cell' => [['generators' => ['g' => ['map' => ['layout' => 'square', 'width' => 1, 'height' => 1]]]], 'f > generators > g > map > cell: a template is a text'];
        yield 'map without layout' => [['generators' => ['g' => ['map' => ['width' => 1, 'height' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map with an unknown layout' => [['generators' => ['g' => ['map' => ['layout' => 'round', 'width' => 1, 'height' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map with a layout that is not a text' => [['generators' => ['g' => ['map' => ['layout' => 1, 'width' => 1, 'height' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map without width' => [['generators' => ['g' => ['map' => ['layout' => 'square', 'height' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map without height' => [['generators' => ['g' => ['map' => ['layout' => 'square', 'width' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map height not whole' => [['generators' => ['g' => ['map' => ['layout' => 'square', 'width' => 1, 'height' => 'x', 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout"'];
        yield 'map width zero' => [['generators' => ['g' => ['map' => ['layout' => 'hex', 'width' => 0, 'height' => 1, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'map height zero' => [['generators' => ['g' => ['map' => ['layout' => 'hex', 'width' => 1, 'height' => 0, 'cell' => 'x']]]], 'f > generators > g > map: a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1'];
        yield 'graph without nodes' => [['generators' => ['g' => ['map' => ['layout' => 'graph', 'cell' => 'x']]]], 'f > generators > g > map: a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0'];
        yield 'graph with zero nodes' => [['generators' => ['g' => ['map' => ['layout' => 'graph', 'nodes' => 0, 'cell' => 'x']]]], 'f > generators > g > map: a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0'];
        yield 'graph with empty nodes' => [['generators' => ['g' => ['map' => ['layout' => 'graph', 'nodes' => '', 'cell' => 'x']]]], 'f > generators > g > map: a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0'];
        yield 'graph with extra edges not whole' => [['generators' => ['g' => ['map' => ['layout' => 'graph', 'nodes' => 2, 'extra_edges' => '1', 'cell' => 'x']]]], 'f > generators > g > map: a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0'];
        yield 'list map with an unknown key' => [['lists' => ['a' => ['entries' => ['x'], 'chain' => true]]], 'f > lists > a: unknown keys "chain"; the keys are "entries", "markov"'];
        yield 'list map without entries' => [['lists' => ['a' => ['markov' => true]]], 'f > lists > a: a list is a sequence of at least one entry'];
        yield 'markov not a map' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => 'yes']]], 'f > lists > a > markov: a map of names is expected'];
        yield 'markov false' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => false]]], 'f > lists > a > markov: a map of names is expected'];
        yield 'markov with an unknown key' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['depth' => 2]]]], 'f > lists > a > markov: unknown keys "depth"; the keys are "order", "min_length", "max_length", "allow_entries"'];
        yield 'markov order not whole' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['order' => '2']]]], 'f > lists > a > markov: a chain is true, or a map whose order and lengths are whole numbers and whose "allow_entries" is true or false'];
        yield 'markov shortest not whole' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['min_length' => 1.5]]]], 'f > lists > a > markov: a chain is true'];
        yield 'markov longest not whole' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['max_length' => 'x']]]], 'f > lists > a > markov: a chain is true'];
        yield 'markov allow_entries not a boolean' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['allow_entries' => 1]]]], 'f > lists > a > markov: a chain is true'];
        yield 'markov order too high' => [['lists' => ['a' => ['entries' => ['x'], 'markov' => ['order' => 7]]]], 'f > lists > a > markov: markov: the order is a whole number from 1 to 6'];
        yield 'markov on a single list' => [['list' => 'a', 'entries' => ['x'], 'markov' => ['max_length' => 65]], 'f > markov: markov: the lengths are whole numbers'];
        yield 'state not a map' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a']]]], 'f > generators > g > state: a map of names is expected'];
        yield 'state value not a template' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a' => 1]]]], 'f > generators > g > state > a: a template is a text'];
        yield 'state value with an unknown key' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a' => ['start' => 'x', 'then' => 'y']]]]], 'f > generators > g > state > a: unknown keys "then"; the keys are "start", "next"'];
        yield 'state value without start' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a' => ['next' => 'y']]]]], 'f > generators > g > state > a > start: a template is a text'];
        yield 'state value with a next that is not a template' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a' => ['start' => 'x', 'next' => 2]]]]], 'f > generators > g > state > a > next: a template is a text'];
        yield 'state value with a bad name' => [['generators' => ['g' => ['templates' => 'x', 'state' => ['a-b' => 'x']]]], 'f > generators > g: g: "a-b" is not a name of a state value'];
        yield 'graph with negative extra edges' => [['generators' => ['g' => ['map' => ['layout' => 'graph', 'nodes' => 2, 'extra_edges' => -1, 'cell' => 'x']]]], 'f > generators > g > map: a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0'];
    }

    #[DataProvider('invalid')]
    public function testItSaysWhereADocumentIsWrong(mixed $document, string $message): void
    {
        $this->expectException(InvalidDefinition::class);

        if ('' !== $message) {
            $this->expectExceptionMessageIsOrContains($message);
        }

        new Reader()->catalog(['f' => $document]);
    }

    public function testNamesAcceptLettersDigitsDashesAndDots(): void
    {
        $catalog = new Reader()->catalog(['f' => ['lists' => ['Name_1.elf-female.x' => ['a'], 'x' => ['b']]]]);

        self::assertTrue($catalog->hasList('Name_1.elf-female.x'));
    }

    public function testANameMayBeMadeOfDigits(): void
    {
        $catalog = new Reader()->catalog(['f' => ['lists' => ['12' => ['a']], 'generators' => ['7' => ['templates' => 'x']]], 'g' => ['list' => 3, 'entries' => ['b']]]);

        self::assertTrue($catalog->hasList('12'));
        self::assertTrue($catalog->hasList('3'));
        self::assertTrue($catalog->hasGenerator('7'));
    }

    public function testItSaysWhatANameIs(): void
    {
        self::assertTrue(Reader::isName('name.elf-female_2'));
        self::assertFalse(Reader::isName('name elf'));
        self::assertFalse(Reader::isName(''));
    }

    public function testANameMustBeWhole(): void
    {
        $this->expectExceptionMessageIsOrContains('f: "a.b " is not a name');

        new Reader()->catalog(['f' => ['lists' => ['a.b ' => ['x']]]]);
    }

    public function testANameEndsWithItsLastPart(): void
    {
        self::assertFalse(Reader::isName("a.b\n"));
    }

    public function testANameStartsWithAPart(): void
    {
        $this->expectExceptionMessageIsOrContains('f: ".b" is not a name');

        new Reader()->catalog(['f' => ['lists' => ['.b' => ['x']]]]);
    }
}
