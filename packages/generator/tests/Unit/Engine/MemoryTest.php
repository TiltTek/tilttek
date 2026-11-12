<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\GeneratorDefinition;
use Tilttek\Generator\Definition\StateValue;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Exception\InvalidCatalog;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\ScriptedEngine;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

#[CoversClass(Generator::class)]
#[CoversClass(Producer::class)]
#[CoversClass(GeneratorDefinition::class)]
#[CoversClass(StateValue::class)]
#[CoversClass(Draw::class)]
#[CoversClass(DrawResult::class)]
final class MemoryTest extends TestCase
{
    private const array COUNTER = [
        'counter' => [
            'state' => [
                'season' => 'spring',
                'day' => ['start' => '1', 'next' => '{{ state.day + 1 }}'],
                'twice' => ['start' => '{{ state.day * 2 }}', 'next' => '{{ state.day * 2 }}'],
            ],
            'vars' => ['label' => 'Day {{ state.day }}'],
            'templates' => '{{ label }} of {{ state.season }} ({{ state.twice }})',
        ],
    ];

    public function testAFirstDrawStartsTheStateAndReturnsIt(): void
    {
        $result = Catalogs::generator([], self::COUNTER)->draw('counter');

        self::assertSame(['Day 1 of spring (2)'], $result->results);
        self::assertSame(['season' => 'spring', 'day' => '1', 'twice' => '2'], $result->state);
        self::assertSame(['season' => 'spring', 'day' => '1', 'twice' => '2'], $result->toArray()['state'] ?? null);
    }

    public function testEachResultStartsFromTheStateTheOneBeforeLeft(): void
    {
        $result = Catalogs::generator([], self::COUNTER)->draw('counter', new Draw(3));

        self::assertSame(['Day 1 of spring (2)', 'Day 2 of spring (4)', 'Day 3 of spring (6)'], $result->results);
        self::assertSame(['season' => 'spring', 'day' => '3', 'twice' => '6'], $result->state);
    }

    public function testADrawGoesOnFromThePreviousState(): void
    {
        $generator = Catalogs::generator([], self::COUNTER);
        $result = $generator->draw('counter', new Draw(2, state: ['day' => 9, 'season' => 'winter', 'twice' => 'x', 'gone' => 'y']));

        self::assertSame(['Day 10 of winter (20)', 'Day 11 of winter (22)'], $result->results);
        self::assertSame(['season' => 'winter', 'day' => '11', 'twice' => '22'], $result->state);
    }

    public function testAValueThePreviousStateLacksStartsAgain(): void
    {
        $result = Catalogs::generator([], self::COUNTER)->draw('counter', new Draw(state: ['day' => '4']));

        self::assertSame(['Day 5 of spring (10)'], $result->results);
        self::assertSame(['season' => 'spring', 'day' => '5', 'twice' => '10'], $result->state);
    }

    public function testTheTemplatesOfTheStateSeeThePreviousOne(): void
    {
        $generators = ['g' => [
            'state' => [
                'a' => ['start' => '1', 'next' => '{{ previous.a + previous.b }}'],
                'b' => ['start' => '1', 'next' => '{{ state.a }}-{{ previous.a }}'],
            ],
            'fields' => ['now' => '{{ state.a }}/{{ state.b }}', 'before' => "{{ previous.a|default('none') }}"],
        ]];
        $generator = Catalogs::generator([], $generators);

        self::assertSame([['now' => '1/1', 'before' => 'none']], $generator->draw('g')->results);
        self::assertSame([['now' => '7/7-3', 'before' => '3']], $generator->draw('g', new Draw(state: ['a' => '3', 'b' => '4']))->results);
    }

    public function testAUniqueDrawDropsARepeatedResultAndTheStateItLeft(): void
    {
        $generators = ['g' => [
            'state' => ['n' => ['start' => '0', 'next' => "{{ state.n + roll('1d2') }}"]],
            'templates' => '{{ state.n // 2 }}',
        ]];
        // n goes 0 (start), then +2 → 2 (result 1), then +1 → 3 (result 1 again: dropped, n stays 2), then +2 → 4.
        $result = Catalogs::generator([], $generators, ScriptedEngine::randomizer(1, 0, 1))->draw('g', new Draw(3, true));

        self::assertSame(['0', '1', '2'], $result->results);
        self::assertSame(['n' => '4'], $result->state);
    }

    public function testAUniqueDrawThatFindsTooFewSaysSo(): void
    {
        $generators = ['g' => ['state' => ['n' => ['start' => 'x', 'next' => 'x']], 'templates' => 'same']];
        $timer = new SteppingTimer(1.0);
        $result = Catalogs::generator([], $generators, limits: new Limits(attempts: 2), timer: $timer)->draw('g', new Draw(2, true));
        // The draw reads the clock when it starts; two results times two attempts each: four results made, each reading
        // it when it starts, when it enters the generator, and before each of its two templates.
        self::assertSame(17.0, $timer->now());

        self::assertSame(['same'], $result->results);
        self::assertTrue($result->isShort());
        self::assertSame(['n' => 'x'], $result->state);
    }

    public function testAStateValueHasALimitedLength(): void
    {
        $generator = Catalogs::generator([], ['g' => ['state' => ['n' => ['start' => 'x', 'next' => '{{ state.n }}{{ state.n }}']], 'templates' => 'y']]);

        self::assertSame(['n' => str_repeat('x', 1024)], $generator->draw('g', new Draw(state: ['n' => str_repeat('x', 512)]))->state);
        self::assertSame(['n' => str_repeat('é', 1024)], $generator->draw('g', new Draw(state: ['n' => str_repeat('é', 512)]))->state);

        $this->expectExceptionObject(LimitExceeded::of('characters per state value', 1024));
        $generator->draw('g', new Draw(state: ['n' => str_repeat('x', 513)]));
    }

    public function testAGeneratorWithoutAStateIgnoresTheOneADrawBrings(): void
    {
        $result = Catalogs::generator([], ['g' => ['templates' => 'plain']])->draw('g', new Draw(state: ['n' => '1']));

        self::assertSame(['plain'], $result->results);
        self::assertNull($result->state);
        self::assertArrayNotHasKey('state', $result->toArray());
    }

    public function testAGeneratorWithAStateCalledByAnotherStartsAgainEachTime(): void
    {
        $generators = self::COUNTER + ['outer' => ['fields' => ['one' => "{{ generate('counter') }}", 'two' => ['generate' => 'counter']]]];

        self::assertSame([['one' => 'Day 1 of spring (2)', 'two' => 'Day 1 of spring (2)']], Catalogs::generator([], $generators)->draw('outer', new Draw(state: ['day' => '5']))->results);
    }

    public function testTheTemplatesOfTheStateUseTheLanguageOfTheGenerator(): void
    {
        $generator = Catalogs::generator(['tree' => [['text' => 'arbre', 'gender' => 'm']]], ['g' => [
            'locale' => 'fr',
            'state' => ['place' => "{{ pick('tree')|article }}"],
            'templates' => '{{ state.place }}',
        ]]);

        self::assertSame(['place' => "l'arbre"], $generator->draw('g')->state);
    }

    public function testTheTemplatesOfTheStateAreCheckedWhenTheCatalogIsBuilt(): void
    {
        try {
            Catalogs::generator([], ['g' => ['state' => ['a' => ['start' => "{{ pick('none') }}", 'next' => "{{ generate('nobody') }}"]], 'templates' => 'x']]);
            self::fail('The catalog is not checked.');
        } catch (InvalidCatalog $invalidCatalog) {
            self::assertSame(['g:state:a:start: the list "none" does not exist', 'g:state:a:next: the generator "nobody" does not exist'], array_map(strval(...), $invalidCatalog->problems()));
        }
    }

    public function testASeededDrawWithTheSameStateComesAgain(): void
    {
        $generators = ['g' => ['state' => ['n' => ['start' => '0', 'next' => "{{ state.n + roll('1d100') }}"]], 'templates' => '{{ state.n }}']];
        $generator = Catalogs::generator([], $generators);
        $draw = new Draw(5, seed: 'campaign', state: ['n' => '10']);

        self::assertSame($generator->draw('g', $draw)->toArray(), $generator->draw('g', $draw)->toArray());
    }
}
