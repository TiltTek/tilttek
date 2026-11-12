<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\EntryList;
use Tilttek\Generator\Definition\GenerateField;
use Tilttek\Generator\Definition\GeneratorDefinition;
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
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Exception\UnknownList;

#[CoversClass(EntryList::class)]
#[CoversClass(Template::class)]
#[CoversClass(TemplateField::class)]
#[CoversClass(GenerateField::class)]
#[CoversClass(TextBody::class)]
#[CoversClass(ObjectBody::class)]
#[CoversClass(GridBody::class)]
#[CoversClass(GraphBody::class)]
#[CoversClass(GeneratorDefinition::class)]
#[CoversClass(Catalog::class)]
#[CoversClass(MarkovChain::class)]
#[CoversClass(StateValue::class)]
final class DefinitionTest extends TestCase
{
    public function testAListHasEntriesAndKnowsItsTags(): void
    {
        $list = new EntryList('weapon', new Entry('épée', 1, [], ['kind' => ['blade']]), new Entry('arc'));

        self::assertSame('weapon', $list->name);
        self::assertCount(2, $list->entries);
        self::assertTrue($list->hasTag('kind'));
        self::assertFalse($list->hasTag('era'));
        self::assertTrue(new EntryList('w', new Entry('a'), new Entry('b', 1, [], ['era' => ['old']]))->hasTag('era'));
        self::assertSame([$entry = new Entry('c')], new EntryList('w', ...['named' => $entry])->entries);
    }

    public function testAListInventsWordsByAChain(): void
    {
        $list = new EntryList('n', new Entry('a', 1, [], ['g' => ['f']]));
        $invented = $list->invent($chain = new MarkovChain(3));

        self::assertNull($list->markov);
        self::assertSame($chain, $invented->markov);
        self::assertSame($list->entries, $invented->entries);
        self::assertSame('n', $invented->name);
        self::assertTrue($invented->hasTag('g'));
    }

    public function testAChainHasDefaultsAndBounds(): void
    {
        $chain = new MarkovChain();

        self::assertSame([2, 3, 16, false], [$chain->order, $chain->minLength, $chain->maxLength, $chain->allowEntries]);
        self::assertSame([1, 1, 1], [new MarkovChain(1, 1, 1)->order, new MarkovChain(1, 1, 1)->minLength, new MarkovChain(1, 1, 1)->maxLength]);
        self::assertSame([6, 64], [new MarkovChain(6, 64, 64)->order, new MarkovChain(6, 64, 64)->maxLength]);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function chains(): iterable
    {
        $order = 'markov: the order is a whole number from 1 to 6';
        $lengths = 'markov: the lengths are whole numbers, the shortest at least 1 and the longest from the shortest to 64';

        yield 'order 0' => [0, 3, 16, $order];
        yield 'order 7' => [7, 3, 16, $order];
        yield 'shortest 0' => [2, 0, 16, $lengths];
        yield 'longest under the shortest' => [2, 5, 4, $lengths];
        yield 'longest 65' => [2, 3, 65, $lengths];
    }

    #[DataProvider('chains')]
    public function testAChainRefusesWhatItCannotWrite(int $order, int $min, int $max, string $message): void
    {
        $this->expectExceptionObject(new InvalidDefinition($message));

        new MarkovChain($order, $min, $max);
    }

    public function testAGeneratorCarriesItsState(): void
    {
        $state = ['weather' => new StateValue('clear', "{{ pick('weather') }}"), '_n2' => new StateValue('0')];
        $generator = new GeneratorDefinition('g', new TextBody([new Template('x')]), ['label' => 'y'], 'fr', $state);

        self::assertSame($state, $generator->state);
        self::assertTrue($generator->hasState());
        self::assertSame("{{ pick('weather') }}", $state['weather']->next);
        self::assertNull($state['_n2']->next);
        self::assertSame('0', $state['_n2']->start);
        self::assertFalse(new GeneratorDefinition('g', new TextBody([new Template('x')]))->hasState());
        self::assertCount(32, new GeneratorDefinition('g', new TextBody([new Template('x')]), state: array_fill_keys(array_map(static fn (int $n): string => 'v'.$n, range(1, 32)), new StateValue('x')))->state);
    }

    public function testAStateHoldsAtMostThirtyTwoValues(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('g', 'a state holds at most 32 values'));

        new GeneratorDefinition('g', new TextBody([new Template('x')]), state: array_fill_keys(array_map(static fn (int $n): string => 'v'.$n, range(1, 33)), new StateValue('x')));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function stateNames(): iterable
    {
        yield ['1a'];
        yield ['a-b'];
        yield [''];
        yield ['a b'];
        yield ["a\n"];
    }

    #[DataProvider('stateNames')]
    public function testAStateValueHasAName(string $name): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('g', \sprintf('"%s" is not a name of a state value: a letter or "_", then letters, digits or "_"', $name)));

        new GeneratorDefinition('g', new TextBody([new Template('x')]), state: [$name => new StateValue('x')]);
    }

    public function testAVariableOfAGeneratorWithAStateIsNotNamedLikeTheState(): void
    {
        self::assertSame(['state' => 'x'], new GeneratorDefinition('g', new TextBody([new Template('x')]), ['state' => 'x'])->vars);

        $this->expectExceptionObject(InvalidDefinition::at('g', 'a generator with a state names no variable or field "state" or "previous"'));

        new GeneratorDefinition('g', new TextBody([new Template('x')]), ['state' => 'x'], state: ['a' => new StateValue('x')]);
    }

    public function testAFieldOfAGeneratorWithAStateIsNotNamedLikeTheState(): void
    {
        self::assertInstanceOf(ObjectBody::class, new GeneratorDefinition('g', new ObjectBody(['previous' => new TemplateField('x')]))->body);

        $this->expectExceptionObject(InvalidDefinition::at('g', 'a generator with a state names no variable or field "state" or "previous"'));

        new GeneratorDefinition('g', new ObjectBody(['previous' => new TemplateField('x')]), state: ['a' => new StateValue('x')]);
    }

    public function testAListIsNotEmpty(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('weapon', 'a list has at least one entry'));

        new EntryList('weapon');
    }

    public function testTemplatesAndFieldsCarryTheirValues(): void
    {
        self::assertSame(1, new Template('x')->weight);
        self::assertSame('x', new Template('x', 2)->source);
        self::assertSame('y', new TemplateField('y')->source);
        self::assertNull(new GenerateField('npc')->count);
        self::assertSame(1, new GenerateField('npc', 1)->count);

        $body = new TextBody([new Template('a')], ['upper']);
        self::assertSame(['upper'], $body->filters);
        self::assertSame([], new TextBody([new Template('a')])->filters);
        self::assertSame(['name' => $field = new TemplateField('n')], new ObjectBody(['name' => $field])->fields);

        $grid = new GridBody(MapLayout::Hex, 1, 1, $field);
        self::assertSame([MapLayout::Hex, 1, 1, $field], [$grid->layout, $grid->width, $grid->height, $grid->cell]);
        $graph = new GraphBody('1d4', 0, $field);
        self::assertSame(['1d4', 0, $field], [$graph->nodes, $graph->extraEdges, $graph->cell]);
        self::assertSame(1, new GraphBody(1, 0, $field)->nodes);

        $definition = new GeneratorDefinition('g', $body);
        self::assertSame([[], null], [$definition->vars, $definition->locale]);
    }

    public function testATemplateWeighsAtLeastOne(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('x', 'the weight of a template is a whole number of at least 1'));

        new Template('x', 0);
    }

    public function testAGeneratedFieldCountsAtLeastOne(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('npc', 'the count of a generated field is a whole number of at least 1'));

        new GenerateField('npc', 0);
    }

    public function testATextGeneratorHasATemplate(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('A text generator has at least one template.'));

        new TextBody([]);
    }

    public function testAStructuredGeneratorHasAField(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('A structured generator has at least one field.'));

        new ObjectBody([]);
    }

    public function testAMapHasACell(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('A map is at least one cell wide and one cell high.'));

        new GridBody(MapLayout::Square, 1, 0, new TemplateField('x'));
    }

    public function testAMapIsAtLeastOneCellWide(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('A map is at least one cell wide and one cell high.'));

        new GridBody(MapLayout::Square, 0, 1, new TemplateField('x'));
    }

    public function testAGraphHasANode(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('A graph has at least one node.'));

        new GraphBody(0, 0, new TemplateField('x'));
    }

    public function testAGraphHasNoNegativePassages(): void
    {
        $this->expectExceptionObject(new InvalidDefinition('The extra passages of a graph are a whole number of at least 0.'));

        new GraphBody(1, -1, new TemplateField('x'));
    }

    public function testACatalogFindsItsListsAndGenerators(): void
    {
        $list = new EntryList('weapon', new Entry('épée'));
        $generator = new GeneratorDefinition('item', new TextBody([new Template('x')]));
        $catalog = new Catalog([$list], [$generator]);

        self::assertTrue($catalog->hasList('weapon'));
        self::assertFalse($catalog->hasList('item'));
        self::assertSame($list, $catalog->list('weapon'));
        self::assertTrue($catalog->hasGenerator('item'));
        self::assertFalse($catalog->hasGenerator('weapon'));
        self::assertSame($generator, $catalog->generator('item'));
        self::assertSame(['weapon' => $list], $catalog->lists());
        self::assertSame(['item' => $generator], $catalog->generators());
        self::assertSame([], new Catalog()->lists());
    }

    public function testAnUnknownListIsAnError(): void
    {
        $this->expectExceptionObject(UnknownList::named('nope'));

        new Catalog()->list('nope');
    }

    public function testAnUnknownGeneratorIsAnError(): void
    {
        $this->expectExceptionObject(UnknownGenerator::named('nope'));

        new Catalog()->generator('nope');
    }

    public function testTwoListsCannotShareAName(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('weapon', 'two lists have this name'));

        new Catalog([new EntryList('weapon', new Entry('a')), new EntryList('weapon', new Entry('b'))]);
    }

    public function testTwoGeneratorsCannotShareAName(): void
    {
        $body = new TextBody([new Template('x')]);
        $this->expectExceptionObject(InvalidDefinition::at('item', 'two generators have this name'));

        new Catalog([], [new GeneratorDefinition('item', $body), new GeneratorDefinition('item', $body)]);
    }
}
