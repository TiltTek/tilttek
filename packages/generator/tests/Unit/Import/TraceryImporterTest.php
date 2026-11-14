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
use Tilttek\Generator\Import\TemplateText;
use Tilttek\Generator\Import\Tracery\Action;
use Tilttek\Generator\Import\Tracery\FreeVariables;
use Tilttek\Generator\Import\Tracery\RuleParser;
use Tilttek\Generator\Import\Tracery\Tag;
use Tilttek\Generator\Import\Tracery\Text;
use Tilttek\Generator\Import\TraceryImporter;
use Tilttek\Generator\Tests\Unit\Fixture\Documents;

#[CoversClass(TraceryImporter::class)]
#[CoversClass(Imported::class)]
#[CoversClass(TemplateText::class)]
#[CoversClass(RuleParser::class)]
#[CoversClass(FreeVariables::class)]
#[CoversClass(Tag::class)]
#[CoversClass(Text::class)]
#[CoversClass(Action::class)]
final class TraceryImporterTest extends TestCase
{
    public function testPlainSymbolsBecomeListsAndTheOthersGenerators(): void
    {
        $imported = new TraceryImporter()->import('{"origin": ["#animal.capitalize# and #animal.a#", "#greeting#"], "greeting": "Hello", "animal": ["owl", "ox"], "noun": ["#animal.s#"], "big": ["#animal.capitalizeAll#"]}');

        self::assertSame('origin', $imported->start);
        self::assertSame([
            'generators' => [
                'origin' => ['locale' => 'en', 'templates' => ["{{ pick('animal')|upper_first }} and {{ pick('animal')|article('indefinite') }}", "{{ pick('greeting') }}"]],
                'noun' => ['locale' => 'en', 'templates' => ["{{ pick('animal')|plural }}"]],
                'big' => ['locale' => 'en', 'templates' => ["{{ pick('animal')|title }}"]],
            ],
            'lists' => [
                'greeting' => ['entries' => ['Hello']],
                'animal' => ['entries' => ['owl', 'ox']],
            ],
        ], $imported->document);

        $generator = new Generator($imported->catalog());
        self::assertSame(['owls', 'oxen'], $this->all($generator, 'noun'));
        self::assertSame(['Owl', 'Ox'], $this->all($generator, 'big'));
    }

    public function testTheSymbolToStartFromIsAlwaysAGenerator(): void
    {
        $imported = new TraceryImporter('names.', new Attribution('A grammar', 'CC0-1.0'), 'fr', 'first')->import('{"first": ["Ana", "Bo{b}"]}');

        self::assertSame('names.first', $imported->start);
        self::assertSame(['generators' => ['names.first' => ['locale' => 'fr', 'templates' => ['Ana', "{{ 'Bo{b}' }}"], 'source' => 'A grammar', 'licence' => 'CC0-1.0']]], $imported->document);
        self::assertSame(['Ana', 'Bo{b}'], $this->all(new Generator($imported->catalog('grammar.json')), 'names.first'));
    }

    public function testAnActionSetsAVariableThatTheGeneratorsDrawnAfterItReceive(): void
    {
        $imported = new TraceryImporter()->import(json_encode([
            'origin' => ['#[hero:#name#][place:#town#,city]story#'],
            'story' => ['#hero.capitalize# reached #place#. #moral#'],
            'moral' => ['#hero# rested.'],
            'name' => ['ada'],
            'town' => ['Ys'],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame([
            "{% set v_hero = (pick('name')) %}{% set v_place = [(pick('town')), 'city'][between(0, 1)] %}{{ generate('story', with: {v_hero: v_hero, v_place: v_place}) }}",
        ], Documents::templates($imported, 'origin'));
        self::assertSame(["{{ v_hero|upper_first }} reached {{ v_place }}. {{ generate('moral', with: {v_hero: v_hero}) }}"], Documents::templates($imported, 'story'));

        $results = new Generator($imported->catalog())->draw('origin', new Draw(20))->results;
        self::assertContains('Ada reached Ys. ada rested.', $results);
        self::assertContains('Ada reached city. ada rested.', $results);
    }

    public function testAVariableSetInARuleIsReadInTheSameRule(): void
    {
        $imported = new TraceryImporter()->import('{"origin": "[n:#name#]#n# and #n#, [e:]#e#!", "name": ["ana", "bo"]}');

        self::assertSame(["{% set v_n = (pick('name')) %}{{ v_n }} and {{ v_n }}, {% set v_e = '' %}{{ v_e }}!"], Documents::templates($imported, 'origin'));
        self::assertContains(new Generator($imported->catalog())->draw('origin')->first(), ['ana and ana, !', 'bo and bo, !']);
    }

    public function testAVariableShadowsTheSymbolOfItsName(): void
    {
        $imported = new TraceryImporter()->import('{"origin": "#[name:Zed]name#", "name": ["ana"]}');

        self::assertSame(["{% set v_name = 'Zed' %}{{ v_name }}"], Documents::templates($imported, 'origin'));
        self::assertSame('Zed', new Generator($imported->catalog())->draw('origin')->first());
    }

    public function testATagWithActionsOnly(): void
    {
        $imported = new TraceryImporter()->import('{"origin": "#[x:a]#<#x#>"}');

        self::assertSame("{% set v_x = 'a' %}<{{ v_x }}>", Documents::templates($imported, 'origin')[0]);
    }

    public function testABackslashKeepsTheNextCharacter(): void
    {
        $imported = new TraceryImporter()->import('{"origin": "\\\\#no\\\\[tag\\\\] it\'s {{ x }} \\\\"}');

        self::assertSame("#no[tag] it's {{ x }} \\", new Generator($imported->catalog())->draw('origin')->first());
    }

    public function testNumbersAreTexts(): void
    {
        self::assertSame(['generators' => ['origin' => ['locale' => 'en', 'templates' => ["{{ pick('n') }}"]]], 'lists' => ['n' => ['entries' => ['1', '2.5']]]], new TraceryImporter()->import('{"origin": "#n#", "n": [1, 2.5]}')->document);
    }

    public function testTheVariablesAGeneratorReadsAreFoundThroughEveryLevel(): void
    {
        $imported = new TraceryImporter()->import('{"origin": "#[a:1][b:2]one#", "one": "#two#", "two": "#[b:3]three#", "three": "#a##b#"}');

        self::assertSame(["{{ generate('two', with: {v_a: v_a}) }}"], Documents::templates($imported, 'one'));
        self::assertSame("{% set v_b = '3' %}{{ generate('three', with: {v_a: v_a, v_b: v_b}) }}", Documents::templates($imported, 'two')[0]);
        self::assertSame('13', new Generator($imported->catalog())->draw('origin')->first());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'not JSON' => ['{', 'tracery: Syntax error'];
        yield 'a list' => ['["a"]', 'tracery: a grammar is an object of 1 to 4096 symbols'];
        yield 'empty' => ['{}', 'tracery: a grammar is an object of 1 to 4096 symbols'];
        yield 'too deep' => ['{"origin": [["a"]]}', 'tracery > origin > 0: a rule is a text'];
        yield 'a bad symbol' => ['{"origin": "a", "a b": "c"}', 'tracery > a b: a symbol is a name of letters, digits, "_" and "-"'];
        yield 'no rule' => ['{"origin": []}', 'tracery > origin: a symbol has a rule, or a sequence of 1 to 4096 rules'];
        yield 'a map of rules' => ['{"origin": {"a": "b"}}', 'tracery > origin: a symbol has a rule, or a sequence of 1 to 4096 rules'];
        yield 'a rule that is not a text' => ['{"origin": [true]}', 'tracery > origin > 0: a rule is a text'];
        yield 'no start' => ['{"other": "a"}', 'tracery: the grammar has no symbol "origin" to start from'];
        yield 'an unknown symbol' => ['{"origin": "#nope#"}', 'tracery > origin: the symbol "nope" does not exist'];
        yield 'a variable never set first' => ['{"origin": "#story#", "story": "#hero#", "other": "[hero:x]"}', 'tracery > origin: the variable "hero" is read before any action sets it'];
        yield 'POP' => ['{"origin": "[hero:POP]"}', 'tracery > origin > 0: POP is not supported: a variable keeps its value for the rest of its rule'];
        yield 'a function action' => ['{"origin": "[go]"}', 'tracery > origin > 0: an action is "[name:rule]"; other actions are not supported'];
        yield 'a function action before a colon' => ['{"origin": "[go] a:b"}', 'tracery > origin > 0: an action is "[name:rule]"; other actions are not supported'];
        yield 'a bad variable' => ['{"origin": "[a-b:c]"}', 'tracery > origin > 0: "a-b" is not a name of a variable: letters, digits and "_"'];
        yield 'an open tag' => ['{"origin": "#a"}', 'tracery > origin > 0: "#" is not closed'];
        yield 'an open action' => ['{"origin": "[a:b"}', 'tracery > origin > 0: "[" is not closed'];
        yield 'an open tag in an action' => ['{"origin": "[a:#b]"}', 'tracery > origin > 0: "#" is not closed'];
        yield 'an unknown modifier' => ['{"origin": "#a.ed#", "a": "x"}', 'tracery > origin > 0: the modifier "ed" is not supported; the modifiers are "capitalize", "capitalizeAll", "a", "s"'];
        yield 'a modifier without symbol' => ['{"origin": "#.s#"}', 'tracery > origin > 0: a modifier follows a symbol'];
        yield 'a bad symbol in a tag' => ['{"origin": "#a b#"}', 'tracery > origin > 0: "a b" is not a symbol'];
        yield 'an action in a value' => ['{"origin": "[a:[b:c]]"}', "tracery > origin: an action inside an action's value is not supported"];
        yield 'a tag with an action in a value' => ['{"origin": "[a:#[b:c]d#]", "d": "x"}', "tracery > origin: an action inside an action's value is not supported"];
        yield 'too deep a nesting' => ['{"origin": "'.str_repeat('[a:', 17).str_repeat(']', 17).'"}', 'tracery > origin > 0: actions and tags nest at most 16 deep'];
    }

    #[DataProvider('refusals')]
    public function testARefusalSaysWhere(string $json, string $message): void
    {
        $this->expectExceptionObject(new InvalidDefinition($message));

        new TraceryImporter()->import($json);
    }

    public function testAGrammarIsBounded(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('tracery', 'a grammar holds at most 1048576 bytes'));

        new TraceryImporter()->import('{"origin": "'.str_repeat('a', TraceryImporter::MAX_BYTES).'"}');
    }

    public function testASymbolMayBeMadeOfDigits(): void
    {
        $imported = new TraceryImporter(start: '1')->import('{"1": "#2#", "2": "two"}');

        self::assertSame(['generators' => ['1' => ['locale' => 'en', 'templates' => ["{{ pick('2') }}"]]], 'lists' => ['2' => ['entries' => ['two']]]], $imported->document);
        self::assertSame('two', new Generator($imported->catalog())->draw('1')->first());
    }

    public function testThePrefixMakesNames(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('tracery > origin', 'a symbol is a name of letters, digits, "_" and "-"'));

        new TraceryImporter('bad prefix.')->import('{"origin": "a"}');
    }

    /**
     * @return list<string>
     */
    private function all(Generator $generator, string $name): array
    {
        $results = Documents::texts($generator->draw($name, new Draw(30, true))->results);
        sort($results);

        return $results;
    }
}
