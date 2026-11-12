<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Engine\GeneratorExtension;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Engine\Run;
use Tilttek\Generator\Exception\ExhaustedList;
use Tilttek\Generator\Exception\GenerationFailed;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Exception\UnknownList;
use Tilttek\Generator\Exception\UnknownTag;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\ScriptedEngine;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

#[CoversClass(Generator::class)]
#[CoversClass(Producer::class)]
#[CoversClass(GeneratorExtension::class)]
#[CoversClass(Run::class)]
final class GeneratorTest extends TestCase
{
    private const array ABC = ['l' => ['a', ['text' => 'b', 'weight' => 2], 'c']];

    public function testAListOfOneEntryIsPickedWithoutChance(): void
    {
        $generator = Catalogs::generator(['one' => ['x'], ...self::ABC], ['g' => ['templates' => "{{ pick('one') }}{{ pick('l') }}{{ pick('l') }}"]], ScriptedEngine::randomizer(1, 3));

        self::assertSame('xbc', $generator->draw('g')->first());
    }

    public function testPicksOnTheSameListWithOtherTagsKeepTheirOwnEntries(): void
    {
        $lists = ['w' => [['text' => 'sword', 'tags' => ['kind' => 'blade']], ['text' => 'bow', 'tags' => ['kind' => 'bow']]]];
        $generators = ['g' => ['templates' => "{{ pick('w', where: {kind: 'blade'}) }} {{ pick('w', where: {kind: 'bow'}) }} {{ pick('w', where: {kind: 'blade'}) }}"]];

        self::assertSame('sword bow sword', Catalogs::generator($lists, $generators)->draw('g')->first());
    }

    public function testSeveralPicksThatFindNothingGiveNothing(): void
    {
        $lists = ['w' => [['text' => 'sword', 'tags' => ['kind' => 'blade']]]];
        $generators = ['g' => ['templates' => "[{{ pick('w', 2, where: {kind: 'bow'}, not: ['axe'])|join(',') }}]"]];

        self::assertSame('[]', Catalogs::generator($lists, $generators)->draw('g')->first());
    }

    public function testASinglePickWithExclusionsDrawsOneEntry(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l', not: ['a']) }}"]], ScriptedEngine::randomizer(0), new Limits(picks: 2));

        self::assertSame('b', $generator->draw('g')->first());
    }

    public function testASinglePickThatFindsNothingIsAnError(): void
    {
        $lists = ['w' => [['text' => 'sword', 'tags' => ['kind' => 'blade']]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('w', where: {kind: 'bow'}) }}"]]);

        $this->expectExceptionObject(ExhaustedList::named('w'));

        $generator->draw('g');
    }

    public function testEachEntryOfSeveralPicksCountsTowardTheLimit(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l', 2)|join(',') }}"]], ScriptedEngine::randomizer(0, 0), new Limits(picks: 1));

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 1));

        $generator->draw('g');
    }

    public function testATemplateIsChosenByWeight(): void
    {
        $generators = ['g' => ['templates' => ['one', ['template' => 'two', 'weight' => 2], 'three']]];
        $generator = Catalogs::generator([], $generators, ScriptedEngine::randomizer(0, 1, 2, 3));

        self::assertSame(['one', 'two', 'two', 'three'], $generator->draw('g', new Draw(4))->results);
    }

    public function testAnEntryIsPickedByWeight(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l') }}"]], ScriptedEngine::randomizer(0, 1, 2, 3));

        self::assertSame(['a', 'b', 'b', 'c'], $generator->draw('g', new Draw(4))->results);
    }

    public function testSeveralEntriesArePickedWithoutDuplicates(): void
    {
        $lists = ['l' => ['a', 'b', 'a', 'c']];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('l', 3)|join(',') }}"]], ScriptedEngine::randomizer(0, 0));

        self::assertSame('a,b,c', $generator->draw('g')->first());
    }

    public function testAPickOfSeveralGivesWhatTheListHas(): void
    {
        $generators = [
            'many' => ['templates' => "{{ pick('l', 5)|join(',') }}"],
            'none' => ['templates' => "[{{ pick('l', 0)|join(',') }}]"],
            'last' => ['templates' => "{{ pick('l', 2, not: ['a', 'b'])|join(',') }}"],
        ];
        $generator = Catalogs::generator(self::ABC, $generators, ScriptedEngine::randomizer(0, 0, 0, 0, 0, 0, 0));

        self::assertSame('a,b,c', $generator->draw('many')->first());
        self::assertSame('[]', $generator->draw('none')->first());
        self::assertSame('c', $generator->draw('last')->first());
    }

    public function testAPickFiltersOnTags(): void
    {
        $lists = ['w' => [
            ['text' => 'sword', 'tags' => ['kind' => 'blade', 'era' => 'old']],
            ['text' => 'bow', 'tags' => ['kind' => 'bow']],
            ['text' => 'axe', 'tags' => ['kind' => ['blade', 'tool'], 'era' => 'new']],
        ]];
        $generators = [
            'blade' => ['templates' => "{{ pick('w', 3, where: {kind: 'blade'})|join(',') }}"],
            'any' => ['templates' => "{{ pick('w', 3, where: {kind: ['bow', 'tool']})|join(',') }}"],
            'new' => ['templates' => "{{ pick('w', 3, where: {kind: 'blade', era: 'new'})|join(',') }}"],
            'positional' => ['templates' => "{{ pick('w', 3, {kind: 'bow'})|join(',') }}"],
        ];
        $generator = Catalogs::generator($lists, $generators, ScriptedEngine::randomizer(...array_fill(0, 10, 0)));

        self::assertSame('sword,axe', $generator->draw('blade')->first());
        self::assertSame('bow,axe', $generator->draw('any')->first());
        self::assertSame('axe', $generator->draw('new')->first());
        self::assertSame('bow', $generator->draw('positional')->first());
    }

    public function testAPickExcludesEntriesAlreadyDrawn(): void
    {
        $generators = [
            'text' => ['templates' => "{{ pick('l', 3, not: 'b')|join(',') }}"],
            'entry' => ['templates' => "{% set x = pick('l') %}{{ pick('l', 3, not: x)|join(',') }}"],
            'nested' => ['templates' => "{% set x = pick('l', 2) %}{{ pick('l', 3, not: [x])|join(',') }}"],
        ];
        $generator = Catalogs::generator(self::ABC, $generators, ScriptedEngine::randomizer(...array_fill(0, 10, 0)));

        self::assertSame('a,c', $generator->draw('text')->first());
        self::assertSame('b,c', $generator->draw('entry')->first());
        self::assertSame('c', $generator->draw('nested')->first());
    }

    public function testASinglePickWithNothingLeftIsAnError(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l', not: ['a', 'b', 'c']) }}"]]);

        $this->expectExceptionObject(ExhaustedList::named('l'));

        $generator->draw('g');
    }

    public function testAListNamedByAVariableMustExist(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{% set name = 'nope' %}{{ pick(name) }}"]]);

        $this->expectExceptionObject(UnknownList::named('nope'));

        $generator->draw('g');
    }

    public function testATagNamedByAVariableMustExist(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{% set tag = 'kind' %}{{ pick('l', where: {(tag): 'x'}) }}"]]);

        $this->expectExceptionObject(UnknownTag::in('l', 'kind'));

        $generator->draw('g');
    }

    public function testAGeneratorNamedByAVariableMustExist(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{% set name = 'nope' %}{{ generate(name) }}"]]);

        $this->expectExceptionObject(UnknownGenerator::named('nope'));

        $generator->draw('g');
    }

    public function testATagMayBeANumber(): void
    {
        $lists = ['l' => [['text' => 'one', 'tags' => ['1' => 'x']], ['text' => 'two', 'tags' => ['1' => 'y']]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('l', where: {1: 'y'}) }}"]]);

        self::assertSame('two', $generator->draw('g')->first());
    }

    public function testAnEntryShowsItsFieldsAndTags(): void
    {
        $lists = ['dish' => [['text' => 'stew', 'price' => 4, 'tags' => ['meal' => 'dinner']]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{% set d = pick('dish') %}{{ d }} {{ d.text }} {{ d.price }} {{ d.tags.meal|join }}"]]);

        self::assertSame('stew stew 4 dinner', $generator->draw('g')->first());
    }

    public function testAnotherGeneratorIsCalledOnceOrSeveralTimesWithoutDuplicates(): void
    {
        $generators = [
            'name' => ['templates' => "{{ pick('l') }}"],
            'one' => ['templates' => "<{{ generate('name') }}>"],
            'three' => ['templates' => "{{ generate('name', 3)|join(',') }}"],
            'five' => ['templates' => "{{ generate('name', 5)|join(',') }}"],
        ];
        $generator = Catalogs::generator(self::ABC, $generators, new Randomizer(new Xoshiro256StarStar(7)));

        self::assertMatchesRegularExpression('/^<[abc]>$/', $this->text($generator->draw('one')));
        $three = explode(',', $this->text($generator->draw('three')));
        sort($three);
        self::assertSame(['a', 'b', 'c'], $three);
        self::assertCount(3, explode(',', $this->text($generator->draw('five'))));
    }

    public function testTheAttemptsAtDifferentResultsAreBounded(): void
    {
        $exact = Catalogs::generator(['l' => ['a', 'b']], ['name' => ['templates' => "{{ pick('l') }}"], 'g' => ['templates' => "{{ generate('name', 2)|join(',') }}"]], ScriptedEngine::randomizer(0, 0), new Limits(attempts: 1));
        self::assertSame('a', $exact->draw('g')->first());

        $generators = ['name' => ['templates' => "{{ pick('l') }}"], 'g' => ['templates' => "{{ generate('name', 2)|join(',') }}"]];
        $generator = Catalogs::generator(['l' => ['a']], $generators, limits: new Limits(attempts: 3));

        self::assertSame('a', $generator->draw('g')->first());
    }

    public function testADiceExpressionRolls(): void
    {
        $generator = Catalogs::generator([], ['g' => ['templates' => "{{ roll('2d6') }} {{ roll('1d4+10, 1d8') }}"]], ScriptedEngine::randomizer(0, 2, 3, 1));

        self::assertSame('4 14', $generator->draw('g')->first());
    }

    public function testARollWithoutTotalFailsTheTemplate(): void
    {
        $dice = new DiceRoller(customDice: new CustomDice(new DieDefinition('coin', new Face('heads', 'Heads'), new Face('tails', 'Tails'))));
        $generator = new Generator(Catalogs::of([], ['g' => ['templates' => "{{ roll('1d{coin}') }}"]]), dice: $dice);

        $this->expectException(GenerationFailed::class);
        $this->expectExceptionMessageIsOrContains('The template "g:template:0" failed: The roll "1d{coin}" has no total.');

        $generator->draw('g');
    }

    public function testARollCountsAsAPick(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('picks per result', 1));

        Catalogs::generator([], ['g' => ['templates' => "{{ roll('1d6') }}"]], limits: new Limits(picks: 1))->draw('g');
    }

    public function testADiceExpressionThatDoesNotParseFailsTheTemplate(): void
    {
        $generator = Catalogs::generator([], ['g' => ['templates' => "{% set e = '2x' %}{{ roll(e) }}"]]);

        try {
            $generator->draw('g');
            self::fail('The roll did not fail.');
        } catch (GenerationFailed $generationFailed) {
            self::assertStringStartsWith('The template "g:template:0" failed: ', $generationFailed->getMessage());
            self::assertInstanceOf(InvalidExpression::class, $generationFailed->getPrevious());
        }
    }

    public function testAnUndefinedVariableFailsTheTemplate(): void
    {
        $generator = Catalogs::generator([], ['g' => ['templates' => '{{ nothing }}']]);

        $this->expectException(GenerationFailed::class);
        $this->expectExceptionMessageIsOrContains('The template "g:template:0" failed: Variable "nothing" does not exist');

        $generator->draw('g');
    }

    public function testTheSandboxRefusesTheMethodsOfAnEntry(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l').toArray() }}"]]);

        $this->expectException(GenerationFailed::class);
        $this->expectExceptionMessageIsOrContains('Calling "toArray" on a "Tilttek\Generator\Definition\Entry" object is not allowed in "g:template:0" at line 1.');

        $generator->draw('g');
    }

    public function testAFilterThatExpectsATextRefusesAList(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l', 2)|upper_first }}"]]);

        $this->expectException(GenerationFailed::class);
        $this->expectExceptionMessageIsOrContains('A text is expected, not array.');

        $generator->draw('g');
    }

    public function testAnUnknownArticleFailsTheTemplate(): void
    {
        $generator = Catalogs::generator([], ['g' => ['templates' => "{{ 'x'|article('possessive') }}"]]);

        $this->expectException(GenerationFailed::class);

        $generator->draw('g');
    }

    public function testTheTextIsCutOfItsExtraSpaces(): void
    {
        $generator = Catalogs::generator([], [
            'g' => ['templates' => "  a  {% if false %}b{% endif %}  c \n d  "],
            'broken' => ['templates' => "  \xFF  b "],
        ]);

        self::assertSame('a c d', $generator->draw('g')->first());
        self::assertSame("\xFF  b", $generator->draw('broken')->first());
    }

    public function testFiltersApplyToTheWholeText(): void
    {
        $generator = Catalogs::generator([], [
            'g' => ['templates' => 'le arbre de le roi', 'filters' => ['elide', 'upper_first'], 'locale' => 'fr'],
            'cap' => ['templates' => 'élan Rapide', 'filters' => ['capitalize']],
            'first' => ['templates' => 'élan Rapide', 'filters' => ['upper_first']],
        ]);

        self::assertSame('Élan Rapide', $generator->draw('first')->first());

        self::assertSame("L'arbre du roi", $generator->draw('g')->first());
        self::assertSame('Élan rapide', $generator->draw('cap')->first());
    }

    public function testTheGrammarFiltersFollowTheLanguageOfTheGenerator(): void
    {
        $lists = [
            'noun' => [['text' => 'épée', 'gender' => 'f'], ['text' => 'cheval', 'plural' => 'chevaux', 'tags' => ['gender' => 'm']]],
            'adj' => [['text' => 'beau', 'f' => 'belle', 'mp' => 'beaux', 'fp' => 'belles'], ['text' => 'ancien']],
            'crowd' => [['text' => 'gens', 'gender' => 'f', 'number' => 'p']],
        ];
        $generators = [
            'fr' => ['locale' => 'fr', 'templates' => implode(' / ', [
                "{% set e = pick('noun', where: {gender: 'm'}) %}{{ e|article }}",
                "{{ pick('noun', not: 'cheval')|article }}",
                "{{ e|article('indefinite', plural: true) }}",
                "{{ 'reine'|article('definite', 'f') }} {{ e|article('definite', 'f') }}",
                "{{ 'héros'|article('definite', 'm', true) }}",
                "{% set a = pick('adj', not: 'ancien') %}{{ a|agree }} {{ a|agree('f') }} {{ a|agree('m', true) }} {{ a|agree('f', true) }}",
                "{% set b = pick('adj', not: 'beau') %}{{ b|agree('f') }} {{ b|agree('f', true) }} {{ b|agree(pick('crowd')) }} {{ b|agree(e) }}",
                "{{ 'roi'|plural }} {{ e|plural }} {{ e|plural(1) }} {{ e|plural(3) }}",
                "{{ 'que il'|elide }}",
            ])],
            'en' => ['templates' => "{{ 'apple'|article('indefinite') }} {{ 'city'|plural }} {{ 'city'|plural(1) }} {{ 'old'|agree('f') }} {{ ('a ' ~ generate('fr'))|elide }}"],
        ];
        $generator = Catalogs::generator($lists, $generators, ScriptedEngine::randomizer(...array_fill(0, 20, 0)));

        self::assertSame(
            "le cheval / l'épée / des chevaux / la reine la cheval / les héros / beau belle beaux belles / ancienne anciennes anciennes ancien / rois chevaux cheval chevaux / qu'il",
            $generator->draw('fr')->first(),
        );
        self::assertStringStartsWith('an apple cities city old a le cheval', $this->text($generator->draw('en')));
    }

    public function testTheOtherBuiltInLanguagesWorkInTemplates(): void
    {
        $lists = [
            'es' => [['text' => 'hacha', 'gender' => 'f']],
            'de' => [['text' => 'Schwert', 'gender' => 'n']],
            'it' => [['text' => 'spettro', 'gender' => 'm']],
            'pt' => [['text' => 'torre', 'gender' => 'f']],
        ];
        $generators = [
            'es' => ['locale' => 'es_MX', 'templates' => "{% set w = pick('es') %}{{ w|article }}, {{ w|article('indefinite') }}, {{ w|article(plural: true) }}, {{ 'viejo'|agree(w) }}, {{ ('el poder de ' ~ w|article)|elide }}"],
            'de' => ['locale' => 'de', 'templates' => "{% set w = pick('de') %}{{ w|article }}, {{ w|article('demonstrative') }}, {{ w|plural(2) }}, {{ 'zu dem Turm'|elide }}"],
            'it' => ['locale' => 'it', 'templates' => "{% set w = pick('it') %}{{ w|article }}, {{ w|article('indefinite', plural: true) }}, {{ ('la paura di ' ~ w|article)|elide }}"],
            'pt' => ['locale' => 'pt_BR', 'templates' => "{% set w = pick('pt') %}{{ w|article }}, {{ ('em ' ~ w|article)|elide }}, {{ 'novo'|agree(w, true) }}, {{ w|plural(0) }}"],
        ];
        $generator = Catalogs::generator($lists, $generators);

        self::assertSame('el hacha, un hacha, las hachas, vieja, el poder del hacha', $generator->draw('es')->first());
        self::assertSame('das Schwert, dieses Schwert, Schwerter, zum Turm', $generator->draw('de')->first());
        self::assertSame('lo spettro, degli spettri, la paura dello spettro', $generator->draw('it')->first());
        self::assertSame('a torre, na torre, novas, torre', $generator->draw('pt')->first());
    }

    public function testAnAdjectiveTakesItsOwnFormsFirst(): void
    {
        $lists = ['adj' => [['text' => 'one', 'm' => 'm-form', 'f' => 'f-form', 'mp' => 'mp-form', 'fp' => 'fp-form']], 'bare' => [['text' => 'two', 'f' => 'f-two']]];
        $template = "{% set a = pick('adj') %}{{ a|agree }} {{ a|agree('f') }} {{ a|agree('m', true) }} {{ a|agree('f', true) }} {% set b = pick('bare') %}{{ b|agree('f') }} {{ b|agree('f', true) }} {{ b|agree('m', true) }}";
        $generator = Catalogs::generator($lists, ['g' => ['templates' => $template, 'locale' => 'fr']]);

        self::assertSame('m-form f-form mp-form fp-form f-two f-twos twos', $generator->draw('g')->first());
    }

    public function testTheDefaultLanguageIsTheGenerators(): void
    {
        $generator = Catalogs::generator([], ['g' => ['templates' => "{{ 'de le roi'|elide }} {{ 'a apple'|elide }}"]], locale: 'fr');

        self::assertSame('du roi a apple', $generator->draw('g')->first());
    }

    public function testVariablesAreSetOnceForTheWholeResult(): void
    {
        $generators = ['g' => [
            'vars' => ['first' => "{{ pick('l') }}", 'second' => "{{ first }}{{ pick('l', not: first) }}"],
            'templates' => '{{ first }}|{{ second }}',
        ]];
        $generator = Catalogs::generator(self::ABC, $generators, ScriptedEngine::randomizer(0, 0));

        self::assertSame('a|ab', $generator->draw('g')->first());
    }

    public function testAStructuredResultFillsItsFieldsInOrder(): void
    {
        $generators = [
            'name' => ['templates' => "{{ pick('l') }}"],
            'inn' => [
                'vars' => ['keeper' => "{{ generate('name') }}"],
                'fields' => [
                    'keeper' => '{{ keeper }}',
                    'name' => 'The {{ keeper }} Inn',
                    'sign' => '{{ name|upper }}',
                    'staff' => ['generate' => 'name', 'count' => 2],
                    'cook' => ['generate' => 'name'],
                    'count' => '{{ staff|length }}',
                ],
            ],
        ];
        $generator = Catalogs::generator(self::ABC, $generators, ScriptedEngine::randomizer(0, 0, 1, 0));

        self::assertSame(['keeper' => 'a', 'name' => 'The a Inn', 'sign' => 'THE A INN', 'staff' => ['a', 'b'], 'cook' => 'a', 'count' => '2'], $generator->draw('inn')->first());
    }

    public function testASquareMapFillsEachCellWithItsCoordinates(): void
    {
        $generator = Catalogs::generator([], ['m' => ['vars' => ['v' => 'z'], 'map' => ['layout' => 'square', 'width' => 2, 'height' => 2, 'cell' => '{{ x }}{{ y }}{{ v }}']]]);

        self::assertSame(['layout' => 'square', 'width' => 2, 'height' => 2, 'cells' => [
            ['x' => 0, 'y' => 0, 'value' => '00z'],
            ['x' => 1, 'y' => 0, 'value' => '10z'],
            ['x' => 0, 'y' => 1, 'value' => '01z'],
            ['x' => 1, 'y' => 1, 'value' => '11z'],
        ]], $generator->draw('m')->first());
    }

    public function testAHexMapGivesOffsetAndAxialCoordinates(): void
    {
        $generators = ['t' => ['templates' => 'plain'], 'm' => ['map' => ['layout' => 'hex', 'width' => 2, 'height' => 4, 'cell' => ['generate' => 't']]]];
        $map = Catalogs::generator([], $generators)->draw('m');

        self::assertSame('hex', $this->structure($map)['layout']);
        self::assertSame(
            [[0, 0, 0, 0, 'plain'], [1, 0, 1, 0, 'plain'], [0, 1, 0, 1, 'plain'], [1, 1, 1, 1, 'plain'], [0, 2, -1, 2, 'plain'], [1, 2, 0, 2, 'plain'], [0, 3, -1, 3, 'plain'], [1, 3, 0, 3, 'plain']],
            array_map(static fn (array $cell): array => [$cell['col'], $cell['row'], $cell['q'], $cell['r'], $cell['value']], $this->rows($map, 'cells')),
        );
    }

    public function testAMapHasAtMostTheCellsTheLimitsAllow(): void
    {
        $generator = Catalogs::generator([], ['m' => ['map' => ['layout' => 'square', 'width' => 3, 'height' => 2, 'cell' => 'x']]], limits: new Limits(cells: 6));
        self::assertCount(6, $this->rows($generator->draw('m'), 'cells'));

        $generator = Catalogs::generator([], ['m' => ['map' => ['layout' => 'square', 'width' => 3, 'height' => 2, 'cell' => 'x']]], limits: new Limits(cells: 5));
        $this->expectExceptionObject(LimitExceeded::of('cells per map', 5));
        $generator->draw('m');
    }

    public function testAGraphJoinsEveryNodeAndAddsLoops(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 6, 'extra_edges' => 3, 'cell' => '{{ index }}/{{ count }}']]];
        $generator = Catalogs::generator([], $generators, new Randomizer(new Xoshiro256StarStar(3)));

        for ($draw = 0; $draw < 20; ++$draw) {
            $graph = $generator->draw('d');
            self::assertSame('graph', $this->structure($graph)['layout']);
            self::assertSame(['0/6', '1/6', '2/6', '3/6', '4/6', '5/6'], array_column($this->rows($graph, 'nodes'), 'value'));
            self::assertSame([0, 1, 2, 3, 4, 5], array_column($this->rows($graph, 'nodes'), 'id'));
            $edges = $this->rows($graph, 'edges');
            self::assertCount(8, $edges);
            $sorted = $edges;
            usort($sorted, static fn (array $a, array $b): int => $a <=> $b);
            self::assertSame($sorted, $edges);

            $reached = [0 => true];
            $seen = [];
            foreach ($edges as [$from, $to]) {
                self::assertIsInt($from);
                self::assertIsInt($to);
                self::assertLessThan($to, $from);
                $reached[$to] = true;
                $seen[$from.'-'.$to] = true;
            }

            self::assertCount(6, $reached);
            self::assertCount(8, $seen);
        }
    }

    public function testTheExtraPassagesOfAGraphJoinAnyTwoNodes(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 4, 'extra_edges' => 2, 'cell' => 'room']]];
        $graph = Catalogs::generator([], $generators, ScriptedEngine::randomizer(0, 1, 2, 0, 2, 3, 0))->draw('d');

        self::assertSame([[0, 1], [0, 2], [0, 3], [1, 2], [2, 3]], $this->rows($graph, 'edges'));
        $edges = $this->structure($graph)['edges'];
        self::assertIsArray($edges);
        self::assertTrue(array_is_list($edges));
    }

    public function testAnExtraPassageReachesTheLastNode(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 4, 'extra_edges' => 1, 'cell' => 'room']]];
        $graph = Catalogs::generator([], $generators, ScriptedEngine::randomizer(0, 1, 2, 0, 3))->draw('d');

        self::assertSame([[0, 1], [0, 3], [1, 2], [2, 3]], $this->rows($graph, 'edges'));
    }

    public function testTheEdgesOfALargeGraphAreInOrder(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 12, 'cell' => 'room']]];
        $graph = Catalogs::generator([], $generators, ScriptedEngine::randomizer(...array_fill(0, 11, 0)))->draw('d');

        self::assertSame([[0, 1], [0, 2], [0, 3], [0, 4], [0, 5], [0, 6], [0, 7], [0, 8], [0, 9], [0, 10], [0, 11]], $this->rows($graph, 'edges'));
    }

    public function testEachExtraPassageCountsAsAPick(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 3, 'extra_edges' => 1, 'cell' => 'room']]];

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 1));

        Catalogs::generator([], $generators, limits: new Limits(picks: 1))->draw('d');
    }

    public function testAGraphTreeLinksEachNodeToAnEarlierOne(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 4, 'cell' => 'room']]];
        $graph = Catalogs::generator([], $generators, ScriptedEngine::randomizer(0, 1, 2))->draw('d');

        self::assertSame([[0, 1], [1, 2], [2, 3]], $this->rows($graph, 'edges'));
    }

    public function testTheExtraPassagesOfAGraphStopWhenItIsComplete(): void
    {
        $generators = ['d' => ['map' => ['layout' => 'graph', 'nodes' => 3, 'extra_edges' => 5, 'cell' => 'room']]];
        $graph = Catalogs::generator([], $generators, new Randomizer(new Xoshiro256StarStar(1)))->draw('d');
        self::assertSame([[0, 1], [0, 2], [1, 2]], $this->rows($graph, 'edges'));

        $pair = Catalogs::generator([], ['d' => ['map' => ['layout' => 'graph', 'nodes' => 2, 'extra_edges' => 1, 'cell' => 'r']]], ScriptedEngine::randomizer(0))->draw('d');
        self::assertSame([[0, 1]], $this->rows($pair, 'edges'));
    }

    public function testAGraphRollsItsNumberOfNodesAndHasAtLeastOne(): void
    {
        $generator = Catalogs::generator([], [
            'rolled' => ['map' => ['layout' => 'graph', 'nodes' => '1d4+1', 'cell' => '{{ index }}']],
            'negative' => ['map' => ['layout' => 'graph', 'nodes' => '1d4-9', 'cell' => '{{ index }}']],
        ], ScriptedEngine::randomizer(2, 0, 0, 0, 3));

        self::assertCount(4, $this->rows($generator->draw('rolled'), 'nodes'));
        self::assertSame([['id' => 0, 'value' => '0']], $this->rows($generator->draw('negative'), 'nodes'));
    }

    public function testAGraphHasAtMostTheNodesTheLimitsAllow(): void
    {
        $generator = Catalogs::generator([], ['d' => ['map' => ['layout' => 'graph', 'nodes' => 4, 'cell' => 'r']]], limits: new Limits(cells: 4));
        self::assertCount(4, $this->rows($generator->draw('d'), 'nodes'));

        $generator = Catalogs::generator([], ['d' => ['map' => ['layout' => 'graph', 'nodes' => 4, 'cell' => 'r']]], limits: new Limits(cells: 3));
        $this->expectExceptionObject(LimitExceeded::of('cells per map', 3));
        $generator->draw('d');
    }

    public function testADrawGivesTheResultsAskedFor(): void
    {
        $generator = Catalogs::generator(['l' => ['a']], ['g' => ['templates' => "{{ pick('l') }}"]]);

        self::assertEquals(new DrawResult('g', ['a', 'a', 'a'], 3), $generator->draw('g', new Draw(3)));
        self::assertEquals(new DrawResult('g', ['a'], 3), $generator->draw('g', new Draw(3, true)));
        self::assertEquals(new DrawResult('g', ['a'], 1), $generator->draw('g'));
    }

    public function testAUniqueDrawStopsAtTheResultsAskedFor(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l') }}"]], ScriptedEngine::randomizer(0, 0, 1));

        self::assertSame(['a', 'b'], $generator->draw('g', new Draw(2, true))->results);
    }

    public function testADrawHasAtMostTheResultsTheLimitsAllow(): void
    {
        $generator = Catalogs::generator(['l' => ['a']], ['g' => ['templates' => 'x']], limits: new Limits(results: 2));
        self::assertCount(2, $generator->draw('g', new Draw(2))->results);

        $this->expectExceptionObject(LimitExceeded::of('results per draw', 2));
        $generator->draw('g', new Draw(3));
    }

    public function testADrawWithASeedComesAgain(): void
    {
        $lists = ['l' => range(1, 50)];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('l') }}-{{ roll('1d100') }}"]]);

        $first = $generator->draw('g', new Draw(5, false, 'table 7'));
        self::assertEquals($first, $generator->draw('g', new Draw(5, false, 'table 7')));
        self::assertNotEquals($first, $generator->draw('g', new Draw(5, false, 'table 8')));
        self::assertSame(['29-39', '18-77', '7-2', '12-90', '18-96'], $first->results);
    }

    public function testADrawWithoutSeedUsesTheGeneratorsRandomness(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l') }}"]], ScriptedEngine::randomizer(3));

        self::assertSame('c', $generator->draw('g')->first());
    }

    public function testAnUnknownGeneratorCannotBeDrawn(): void
    {
        $this->expectExceptionObject(UnknownGenerator::named('nope'));

        Catalogs::generator([], ['g' => ['templates' => 'x']])->draw('nope');
    }

    public function testGeneratorsInsideEachOtherStopAtTheDepthLimit(): void
    {
        $generators = ['a' => ['templates' => "a{{ generate('b') }}"], 'b' => ['templates' => "b{{ generate('c') }}"], 'c' => ['templates' => 'c']];

        self::assertSame('abc', Catalogs::generator([], $generators, limits: new Limits(depth: 3))->draw('a')->first());

        $this->expectExceptionObject(LimitExceeded::of('generators called inside each other', 2));
        Catalogs::generator([], $generators, limits: new Limits(depth: 2))->draw('a');
    }

    public function testARecursiveGeneratorStops(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('generators called inside each other', 8));

        Catalogs::generator([], ['loop' => ['templates' => "{{ generate('loop') }}"]])->draw('loop');
    }

    public function testAResultStopsAtThePickLimit(): void
    {
        $generators = ['g' => ['templates' => "{{ pick('l') }}{{ pick('l') }}"]];
        self::assertSame(2, \strlen($this->text(Catalogs::generator(self::ABC, $generators, limits: new Limits(picks: 3))->draw('g'))));

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 2));
        Catalogs::generator(self::ABC, $generators, limits: new Limits(picks: 2))->draw('g');
    }

    public function testEachResultHasItsOwnPicks(): void
    {
        $generator = Catalogs::generator(self::ABC, ['g' => ['templates' => "{{ pick('l') }}"]], limits: new Limits(picks: 2));

        self::assertCount(5, $generator->draw('g', new Draw(5))->results);
    }

    public function testAResultStopsAtTheTimeLimit(): void
    {
        // The clock is read when the draw starts, when the result starts, when it enters the generator, before the
        // template is rendered and at each pick: four milliseconds after the result started.
        $generators = ['g' => ['templates' => "{{ pick('l') }}{{ pick('l') }}"]];
        self::assertNotNull(Catalogs::generator(self::ABC, $generators, limits: new Limits(milliseconds: 4), timer: new SteppingTimer(1.0))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('milliseconds per result', 3));
        Catalogs::generator(self::ABC, $generators, limits: new Limits(milliseconds: 3), timer: new SteppingTimer(1.0))->draw('g');
    }

    public function testATemplateWithoutPicksIsTimedBeforeItIsRendered(): void
    {
        // A result of plain text picks nothing: read when the draw starts, when the result starts, when it enters the
        // generator, and before its template is rendered.
        $generators = ['g' => ['templates' => 'plain']];
        self::assertSame('plain', Catalogs::generator(self::ABC, $generators, limits: new Limits(milliseconds: 2), timer: new SteppingTimer(1.0))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('milliseconds per result', 1));
        Catalogs::generator(self::ABC, $generators, limits: new Limits(milliseconds: 1), timer: new SteppingTimer(1.0))->draw('g');
    }

    public function testADrawStopsAtItsTimeLimit(): void
    {
        // Each result of plain text reads the clock three times, after the draw has started: a draw of three results
        // reaches nine milliseconds, whatever each result takes.
        $generators = ['g' => ['templates' => 'plain']];
        self::assertCount(3, Catalogs::generator(self::ABC, $generators, limits: new Limits(drawMilliseconds: 9), timer: new SteppingTimer(1.0))->draw('g', new Draw(3))->results);
        self::assertSame(1000, new Limits()->drawMilliseconds);

        $this->expectExceptionObject(LimitExceeded::of('milliseconds per draw', 8));
        Catalogs::generator(self::ABC, $generators, limits: new Limits(drawMilliseconds: 8), timer: new SteppingTimer(1.0))->draw('g', new Draw(3));
    }

    public function testAUniqueDrawStopsAtTheTimeLimitOfTheDraw(): void
    {
        // Ten attempts at two different results of a generator that always gives the same one: thirty readings.
        $generators = ['g' => ['templates' => 'same']];
        self::assertSame(['same'], Catalogs::generator(self::ABC, $generators, limits: new Limits(drawMilliseconds: 60), timer: new SteppingTimer(1.0))->draw('g', new Draw(2, true))->results);

        $this->expectExceptionObject(LimitExceeded::of('milliseconds per draw', 59));
        Catalogs::generator(self::ABC, $generators, limits: new Limits(drawMilliseconds: 59), timer: new SteppingTimer(1.0))->draw('g', new Draw(2, true));
    }

    public function testTheCatalogIsKept(): void
    {
        $catalog = Catalogs::of(self::ABC, ['g' => ['templates' => 'x']]);

        self::assertSame($catalog, new Generator($catalog)->catalog());
        self::assertSame('x', new Generator($catalog)->draw('g')->first());
    }

    private function text(DrawResult $result): string
    {
        $first = $result->first();
        self::assertIsString($first);

        return $first;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function structure(DrawResult $result): array
    {
        $first = $result->first();
        self::assertIsArray($first);

        return $first;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(DrawResult $result, string $key): array
    {
        $value = $this->structure($result)[$key] ?? null;
        self::assertIsArray($value);
        $rows = [];

        foreach ($value as $row) {
            self::assertIsArray($row);
            $rows[] = $row;
        }

        return $rows;
    }
}
