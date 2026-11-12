<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use RuntimeException;
use stdClass;
use Stringable;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Engine\Run;
use Tilttek\Generator\Exception\ExhaustedList;
use Tilttek\Generator\Exception\GenerationFailed;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Exception\UnknownList;
use Tilttek\Generator\Exception\UnknownTag;
use Tilttek\Generator\Limits;
use Tilttek\Generator\SystemTimer;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

#[CoversClass(Limits::class)]
#[CoversClass(Draw::class)]
#[CoversClass(DrawResult::class)]
#[CoversClass(Run::class)]
#[CoversClass(SystemTimer::class)]
#[CoversClass(InvalidDefinition::class)]
#[CoversClass(UnknownGenerator::class)]
#[CoversClass(UnknownList::class)]
#[CoversClass(UnknownTag::class)]
#[CoversClass(ExhaustedList::class)]
#[CoversClass(LimitExceeded::class)]
#[CoversClass(GenerationFailed::class)]
final class ValuesTest extends TestCase
{
    public function testTheLimitsHaveDefaults(): void
    {
        $limits = new Limits();

        self::assertSame([8, 1000, 250, 10, 100, 2500, 1000], [$limits->depth, $limits->picks, $limits->milliseconds, $limits->attempts, $limits->results, $limits->cells, $limits->drawMilliseconds]);
        self::assertSame(1, new Limits(1, 1, 1, 1, 1, 1, 1)->drawMilliseconds);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function limits(): iterable
    {
        yield ['depth'];
        yield ['picks'];
        yield ['milliseconds'];
        yield ['attempts'];
        yield ['results'];
        yield ['cells'];
        yield ['drawMilliseconds'];
    }

    #[DataProvider('limits')]
    public function testEachLimitIsAtLeastOne(string $limit): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('limits', \sprintf('the limit "%s" is a whole number of at least 1', $limit)));

        new Limits(...[$limit => 0]);
    }

    public function testADrawAsksForOneResultByDefault(): void
    {
        $draw = new Draw();

        self::assertSame([1, false, null], [$draw->count, $draw->unique, $draw->seed]);
        self::assertSame([1, true, 'x'], [new Draw(1, true, 'x')->count, true, new Draw(1, true, 'x')->seed]);
    }

    public function testADrawAsksForAtLeastOneResult(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a draw asks for at least one result'));

        new Draw(0);
    }

    public function testASeedIsNotEmpty(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a seed is not empty'));

        new Draw(1, false, '');
    }

    public function testASeedIsWrittenInUtf8(): void
    {
        self::assertSame('épée', new Draw(1, false, 'épée')->seed);

        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a seed is written in UTF-8'));

        new Draw(1, false, "\xC3\x28");
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidUtf8(): iterable
    {
        yield 'a value' => [['weather' => "rain\xFF"]];
        yield 'a name' => [["w\xE9ather" => 'rain']];
    }

    /**
     * @param array<string, string> $state
     */
    #[DataProvider('invalidUtf8')]
    public function testAStateIsWrittenInUtf8(array $state): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a state is written in UTF-8'));

        new Draw(state: $state);
    }

    public function testADrawBringsAStateAsTexts(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'told';
            }
        };
        $draw = new Draw(state: ['a' => 1, 'b' => 2.5, 'c' => true, 'd' => 'x', 'e' => $stringable, 'f' => str_repeat('é', 1024)]);

        self::assertNull(new Draw()->state);
        self::assertSame([], new Draw(state: [])->state);
        self::assertSame(['a' => '1', 'b' => '2.5', 'c' => '1', 'd' => 'x', 'e' => 'told', 'f' => str_repeat('é', 1024)], $draw->state);
        self::assertCount(32, new Draw(state: array_fill_keys(array_map(static fn (int $n): string => 'v'.$n, range(1, 32)), 'x'))->state ?? []);
    }

    public function testAStateHoldsAtMostThirtyTwoValues(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a state holds at most 32 values'));

        new Draw(state: array_fill_keys(array_map(static fn (int $n): string => 'v'.$n, range(1, 33)), 'x'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function states(): iterable
    {
        yield 'a key that is a number' => [['x']];
        yield 'an array' => [['a' => ['x']]];
        yield 'null' => [['a' => null]];
        yield 'an object' => [['a' => new stdClass()]];
        yield 'a long text' => [['a' => str_repeat('é', 1025)]];
    }

    /**
     * @param array<array-key, mixed> $state
     */
    #[DataProvider('states')]
    public function testAStateIsAMapOfNamesToShortTexts(array $state): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a state is a map of names to texts of at most 1024 characters'));

        new Draw(state: $state);
    }

    public function testADrawResultCarriesTheStateOfAGeneratorWithAMemory(): void
    {
        $result = new DrawResult('g', ['a'], 1, ['n' => '2']);

        self::assertSame(['n' => '2'], $result->state);
        self::assertSame(['generator' => 'g', 'results' => ['a'], 'requested' => 1, 'short' => false, 'state' => ['n' => '2']], $result->toArray());
        self::assertSame(['generator' => 'g', 'results' => ['a'], 'requested' => 1, 'short' => false, 'state' => []], new DrawResult('g', ['a'], 1, [])->toArray());
        self::assertNull(new DrawResult('g', ['a'], 1)->state);
    }

    public function testADrawResultSaysWhenItIsShort(): void
    {
        $result = new DrawResult('g', ['a', ['b' => 'c']], 3);

        self::assertTrue($result->isShort());
        self::assertSame('a', $result->first());
        self::assertSame(['generator' => 'g', 'results' => ['a', ['b' => 'c']], 'requested' => 3, 'short' => true], $result->toArray());
        self::assertFalse(new DrawResult('g', ['a', 'b'], 2)->isShort());
        self::assertNull(new DrawResult('g', [], 0)->first());
    }

    public function testARunKeepsTheLanguageOfTheGeneratorItIsIn(): void
    {
        $run = new Run(new Randomizer(), new Limits(), new SteppingTimer(0.0), 'en');

        self::assertSame('en', $run->locale());
        $run->enter('fr');
        self::assertSame('fr', $run->locale());
        $run->enter('de');
        self::assertSame('de', $run->locale());
        $run->leave();
        self::assertSame('fr', $run->locale());
        $run->leave();
        self::assertSame('en', $run->locale());
    }

    public function testARunCountsEachEntryAsAPick(): void
    {
        $run = new Run(new Randomizer(), new Limits(picks: 1), new SteppingTimer(0.0), 'en');
        $run->enter('fr');

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 1));
        $run->tick();
    }

    public function testTheSystemTimerMovesForward(): void
    {
        $timer = new SystemTimer();
        $first = $timer->now();
        usleep(2000);
        $second = $timer->now();

        self::assertGreaterThan($first + 1.0, $second);
        self::assertLessThan($first + 1000.0, $second);
    }

    public function testTheExceptionsSayWhatIsWrong(): void
    {
        self::assertSame('here: wrong', InvalidDefinition::at('here', 'wrong')->getMessage());
        self::assertSame('No generator is named "g".', UnknownGenerator::named('g')->getMessage());
        self::assertSame('No list is named "l".', UnknownList::named('l')->getMessage());
        self::assertSame('No entry of the list "l" has the tag "t".', UnknownTag::in('l', 't')->getMessage());
        self::assertSame('No entry of the list "l" is left to pick.', ExhaustedList::named('l')->getMessage());
        self::assertSame('The list "l" invented no word that fits in 50 attempts.', ExhaustedList::invented('l', 50)->getMessage());
        self::assertSame('The limit of 3 picks is exceeded.', LimitExceeded::of('picks', 3)->getMessage());
        $previous = new RuntimeException('boom');
        $failed = GenerationFailed::in('g:template:0', $previous);
        self::assertSame('The template "g:template:0" failed: boom', $failed->getMessage());
        self::assertSame($previous, $failed->getPrevious());
        self::assertSame(0, $failed->getCode());
    }
}
