<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Engine\GeneratorExtension;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Engine\Run;
use Tilttek\Generator\Engine\StepNode;
use Tilttek\Generator\Engine\StepVisitor;
use Tilttek\Generator\Exception\GenerationFailed;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\Documents;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

/**
 * The budget of a result checked inside its templates: the steps of loops and assignments, the memory and the time at
 * each step, the texts `replace` and `join` make; and `between` and the variables `generate` gives.
 */
#[CoversClass(Run::class)]
#[CoversClass(StepVisitor::class)]
#[CoversClass(StepNode::class)]
#[CoversClass(GeneratorExtension::class)]
#[CoversClass(Producer::class)]
#[CoversClass(Limits::class)]
final class BudgetTest extends TestCase
{
    private const array ABC = ['l' => ['a', 'b', 'c']];

    private const string NESTED = "{% for x in pick('l', 3) %}{% for y in pick('l', 3) %}.{% endfor %}{% endfor %}";

    public function testEachTurnOfALoopIsAStep(): void
    {
        // Three turns outside, nine inside.
        self::assertSame('.........', $this->generator(self::NESTED, new Limits(steps: 12))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('steps per result', 11));

        $this->generator(self::NESTED, new Limits(steps: 11))->draw('g');
    }

    public function testEachAssignmentIsAStep(): void
    {
        self::assertSame('3', $this->generator('{% set a = 1 %}{% set a = a + 1 %}{% set a = a + 1 %}{{ a }}', new Limits(steps: 3))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('steps per result', 2));

        $this->generator('{% set a = 1 %}{% set a = a + 1 %}{% set a = a + 1 %}{{ a }}', new Limits(steps: 2))->draw('g');
    }

    public function testTheStepsCountAcrossTheTemplatesOfAResult(): void
    {
        $generator = Catalogs::generator(self::ABC, [
            'g' => ['vars' => ['v' => '{% set a = 1 %}{{ a }}'], 'templates' => "{% set b = 2 %}{{ generate('h') }}"],
            'h' => ['templates' => '{% set c = 3 %}{{ c }}'],
        ], limits: new Limits(steps: 2));

        $this->expectExceptionObject(LimitExceeded::of('steps per result', 2));

        $generator->draw('g');
    }

    public function testTheStepsStartAgainForEachResult(): void
    {
        self::assertCount(3, $this->generator('{% set a = 1 %}{{ a }}', new Limits(steps: 1))->draw('g', new Draw(3))->results);
    }

    public function testATextThatDoublesStopsAtTheLimitOfMemory(): void
    {
        $doubling = "{% set s = 'ab' %}".str_repeat("{% for x in pick('l', 3) %}", 4).'{% set s = s ~ s %}'.str_repeat('{% endfor %}', 4).'{{ s|length }}';

        // 3⁴ doublings: far past any memory, stopped at the step that crosses the limit.
        $this->expectExceptionObject(LimitExceeded::of('megabytes per result', 1));

        $this->generator($doubling, new Limits(megabytes: 1))->draw('g');
    }

    public function testATextThatGrowsWithinTheLimitOfMemoryIsMade(): void
    {
        $doubling = "{% set s = 'ab' %}{% for x in pick('l', 3) %}{% for y in pick('l', 3) %}{% set s = s ~ s %}{% endfor %}{% endfor %}{{ s|length }}";

        self::assertSame((string) (2 * 2 ** 9), $this->generator($doubling, new Limits(megabytes: 1))->draw('g')->first());
    }

    public function testTheTimeIsCheckedAtEachStep(): void
    {
        // No pick: only the steps read the clock, one millisecond each.
        $template = str_repeat('{% set a = 1 %}', 10).'{{ a }}';
        $generator = Catalogs::generator([], ['g' => ['templates' => $template]], limits: new Limits(milliseconds: 5), timer: new SteppingTimer(1.0));

        $this->expectExceptionObject(LimitExceeded::of('milliseconds per result', 5));

        $generator->draw('g');
    }

    public function testReplaceIsBoundedBeforeItReplaces(): void
    {
        // Five letters, each grown by one: ten bytes.
        self::assertSame('bbbbbbbbbb', $this->generator("{{ 'aaaaa'|replace({'a': 'bb'}) }}", new Limits(bytes: 10))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('bytes made by "replace"', 10));

        $this->generator("{{ 'aaaaab'|replace({'a': 'bb'}) }}", new Limits(bytes: 10))->draw('g');
    }

    public function testReplaceCountsEverySearch(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('bytes made by "replace"', 10));

        // 6 bytes, then a and b grown by one each: 4 + 2 = 12.
        $this->generator("{{ 'aaaabb'|replace({'a': 'xx', 'b': 'yy'}) }}", new Limits(bytes: 10))->draw('g');
    }

    public function testReplaceThatShortensIsBoundedByItsText(): void
    {
        self::assertSame('bb', $this->generator("{{ 'aaaa'|replace({'aa': 'b'}) }}", new Limits(bytes: 4))->draw('g')->first());
    }

    public function testReplaceWorksLikeTwigsOwn(): void
    {
        self::assertSame('a-c 2 x', $this->generator("{{ 'abc 1 x'|replace({'b': '-', '': 'z', 1: 2, 'x': null})|trim }} x")->draw('g')->first());
        self::assertSame('', $this->generator("{{ none|replace({'a': 'b'}) }}")->draw('g')->first());
        self::assertSame('b', $this->generator("{{ pick('l', where: {}, not: ['b', 'c'])|replace({'a': 'b'}) }}")->draw('g')->first());
    }

    public function testJoinIsBounded(): void
    {
        self::assertSame('ab-cd', $this->generator("{{ ['ab', 'cd']|join('-') }}", new Limits(bytes: 5))->draw('g')->first());

        $this->expectExceptionObject(LimitExceeded::of('bytes made by "join"', 5));

        $this->generator("{{ ['ab', 'cd']|join('--') }}", new Limits(bytes: 5))->draw('g');
    }

    public function testJoinCountsTheLongerOfItsTwoGlues(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('bytes made by "join"', 4));

        $this->generator("{{ ['a', 'b']|join('', ' & ') }}", new Limits(bytes: 4))->draw('g');
    }

    public function testJoinWorksLikeTwigsOwn(): void
    {
        self::assertSame('a, b and c', $this->generator("{{ ['a', 'b', 'c']|join(', ', ' and ') }}")->draw('g')->first());
        self::assertSame('a, b, c', $this->generator("{{ ['a', 'b', 'c']|join(', ', ', ') }}")->draw('g')->first());
        self::assertSame('abc', $this->generator("{{ pick('l', 3)|join|length == 3 ? 'abc' : 'no' }}")->draw('g')->first());
        self::assertSame('a', $this->generator("{{ ['a']|join(', ', ' and ') }}")->draw('g')->first());
        self::assertSame('[]', $this->generator("[{{ []|join(', ') }}]")->draw('g')->first());
        self::assertSame('7', $this->generator('{{ 7|join }}')->draw('g')->first());
        self::assertSame('1-2', $this->generator("{{ {x: 1, y: 2}|join('-') }}")->draw('g')->first());
    }

    public function testBetweenGivesAWholeNumberInItsRange(): void
    {
        self::assertSame('3', $this->generator('{{ between(3, 3) }}')->draw('g')->first());

        foreach ($this->generator('{{ between(-2, 2) }}')->draw('g', new Draw(20))->results as $number) {
            self::assertContains($number, ['-2', '-1', '0', '1', '2']);
        }
    }

    public function testBetweenCountsAsAPick(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('picks per result', 1));

        $this->generator('{{ between(1, 2) }}{{ between(1, 2) }}', new Limits(picks: 1))->draw('g');
    }

    public function testAnEmptyRangeFails(): void
    {
        $this->expectException(GenerationFailed::class);
        $this->expectExceptionMessageIsOrContains('The template "g:template:0" failed: The range from 5 to 3 is empty.');

        $this->generator('{{ between(5, 3) }}')->draw('g');
    }

    public function testGenerateGivesVariables(): void
    {
        $generator = Catalogs::generator([], [
            'g' => ['templates' => "{{ generate('h', with: {hero: 'Ada', n: 2}) }}"],
            'h' => ['vars' => ['shout' => '{{ hero|upper }}'], 'templates' => '{{ shout }} {{ n }}'],
        ]);

        self::assertSame('ADA 2', $generator->draw('g')->first());
    }

    public function testGenerateGivesVariablesToEachOfSeveralResults(): void
    {
        $generator = Catalogs::generator(self::ABC, [
            'g' => ['templates' => "{{ generate('h', 3, {hero: 'Ada'})|join(',') }}"],
            'h' => ['templates' => "{{ hero }}{{ pick('l') }}"],
        ]);

        self::assertCount(3, explode(',', Documents::text($generator->draw('g')->first())));
    }

    public function testTheNamesOfTheVariablesAGeneratorReceivesAreChecked(): void
    {
        foreach (['loop', 'state', 'previous', '_x', '1x', 'a-b', '0'] as $name) {
            $generator = Catalogs::generator([], [
                'g' => ['templates' => "{{ generate('h', with: {'".$name."': 1}) }}"],
                'h' => ['templates' => 'x'],
            ]);

            try {
                $generator->draw('g');
                self::fail(\sprintf('The name "%s" was given.', $name));
            } catch (GenerationFailed $generationFailed) {
                self::assertStringContainsString(\sprintf('"%s" is not a name of a variable given to a generator.', $name), $generationFailed->getMessage());
            }
        }
    }

    public function testReplaceDoesNotCountWhatItShortens(): void
    {
        // Forty bytes; 'x' shortens each of them, 'xx' grows each of its twenty occurrences by four: 40 + 80.
        $this->expectExceptionObject(LimitExceeded::of('bytes made by "replace"', 100));

        $this->generator("{{ '".str_repeat('x', 40)."'|replace({'x': '', 'xx': 'xxxxxx'}) }}", new Limits(bytes: 100))->draw('g');
    }

    public function testJoinWritesItsValuesAsTexts(): void
    {
        self::assertSame('a and b', $this->generator("{{ {x: 'a', y: 'b'}|join(', ', ' and ') }}")->draw('g')->first());
        self::assertSame('', $this->generator("{{ []|join(', ', ' and ') }}")->draw('g')->first());
        self::assertSame('1.5, 1', $this->generator("{{ [1.5, true]|join(', ') }}")->draw('g')->first());

        try {
            $this->generator("{{ ['a', none]|join('-') }}")->draw('g');
            self::fail('A null was joined.');
        } catch (GenerationFailed $generationFailed) {
            self::assertStringContainsString('A text is expected, not null.', $generationFailed->getMessage());
        }
    }

    public function testTheNameOfAVariableGivenToAGeneratorEndsAtItsEnd(): void
    {
        $generator = Catalogs::generator([], [
            'g' => ['templates' => "{{ generate('h', with: {\"a\\n\": 1}) }}"],
            'h' => ['templates' => 'x'],
        ]);

        $this->expectException(GenerationFailed::class);

        $generator->draw('g');
    }

    public function testTheStepsComeAfterTheSandbox(): void
    {
        self::assertSame(10, new StepVisitor()->getPriority());
    }

    public function testTheNewLimitsAreAtLeastOne(): void
    {
        foreach (['steps', 'bytes', 'megabytes'] as $limit) {
            try {
                new Limits(...[$limit => 0]);
                self::fail(\sprintf('The limit "%s" took 0.', $limit));
            } catch (InvalidDefinition $invalidDefinition) {
                self::assertSame(\sprintf('limits: the limit "%s" is a whole number of at least 1', $limit), $invalidDefinition->getMessage());
            }
        }

        $limits = new Limits();
        self::assertSame([10_000, 65_536, 16], [$limits->steps, $limits->bytes, $limits->megabytes]);
    }

    private function generator(string $template, Limits $limits = new Limits()): Generator
    {
        return Catalogs::generator(self::ABC, ['g' => ['templates' => $template]], limits: $limits);
    }
}
