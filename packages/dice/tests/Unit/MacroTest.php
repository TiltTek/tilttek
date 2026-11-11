<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Parser\DiceParser;

#[CoversClass(DiceParser::class)]
#[CoversClass(DiceRoller::class)]
final class MacroTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inputs(): iterable
    {
        yield 'an operand' => ['$attack+2', '1d20+5+2'];
        yield 'an operand that binds looser' => ['$attack*2', '(1d20+5)*2'];
        yield 'a name in capitals, with digits' => ['$Attack2-1', '1d20+7-1'];
        yield 'a macro naming another, its tree kept' => ['$fireball', '8d6+(1d20+5)'];
        yield 'a macro of several instructions alone' => ['$init', '1d20, $1+2'];
        yield 'a macro of several instructions alone, with a comment' => ['$init # who first', '1d20, $1+2'];
        yield 'a macro in an instruction' => ['1d6; $attack', '1d6, 1d20+5'];
        yield 'a macro in a branch' => ['1d20i[>10]{$attack}', '1d20i[>10]{1d20+5}'];
        yield 'a reference is no macro' => ['1d6; $1', '1d6, $1'];
    }

    #[DataProvider('inputs')]
    public function testAMacroIsTheTreeItsNameStandsFor(string $input, string $canonical): void
    {
        $dice = new DiceRoller();

        self::assertSame($canonical, $dice->write($dice->parse($input, macros: $this->macros())));
    }

    public function testAMacroOfSeveralInstructionsKeepsItsComments(): void
    {
        $pool = new DiceRoller()->parse('$init', macros: $this->macros());

        self::assertSame([1 => ['first']], $pool->comments);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function errors(): iterable
    {
        yield 'no macro of that name' => ['1+$sword', 2, 'No macro is named "sword"'];
        yield 'a name of 32 characters' => ['$a'.str_repeat('b', 31), 0, \sprintf('No macro is named "a%s"', str_repeat('b', 31))];
        yield 'a name too long' => ['$a'.str_repeat('b', 32), 0, \sprintf('A macro is named by a letter, then letters and digits, 32 at most; "a%s" is not', str_repeat('b', 32))];
        yield 'several instructions in an expression' => ['$init+1', 0, 'The macro "init" holds several instructions: it is written alone'];
        yield 'several instructions after another' => ['1d6; $init', 5, 'The macro "init" holds several instructions: it is written alone'];
        yield 'a macro that does not parse' => ['2*$broken', 2, 'The macro "broken" does not parse: Unexpected "x"'];
        yield 'a text in arithmetic' => ['$greeting+1', 9, 'A text or a list is not a number to compute with'];
        yield 'a sign apart from its name' => ['$ attack', 2, 'Unexpected "a"'];
    }

    #[DataProvider('errors')]
    public function testAMacroIsRefusedWithItsPosition(string $input, int $position, string $reason): void
    {
        try {
            new DiceRoller()->parse($input, macros: $this->macros());
            self::fail('The input parses.');
        } catch (InvalidExpression $invalidExpression) {
            self::assertSame([$position, $reason], [$invalidExpression->position, $invalidExpression->reason]);
        }
    }

    public function testWithoutMacrosANameIsNotRead(): void
    {
        $this->expectExceptionObject(InvalidExpression::at('$attack', 1, 'Unexpected "a"'));

        new DiceRoller()->parse('$attack');
    }

    public function testAWholeMacroIsWithinTheLimits(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('expressions of a pool', 1, 2));

        new DiceParser(new Limits(expressions: 1), macros: $this->macros())->parse('$init');
    }

    public function testAParserReadsAgainFromAFreshState(): void
    {
        $parser = new DiceParser(macros: $this->macros());
        $parser->parse('$init');

        self::assertSame('1d6', new DiceRoller()->write($parser->parse('1d6')));
    }

    private function macros(): Macros
    {
        return new class implements Macros {
            private const array MACROS = [
                'attack' => '1d20+5',
                'attack2' => '1d20+7',
                'fireball' => '8d6+$attack',
                'init' => '1d20; $1+2 # first',
                'broken' => '2x',
                'greeting' => '"hello"',
            ];

            #[Override]
            public function pool(string $name): ?Pool
            {
                return \array_key_exists($name, self::MACROS) ? new DiceParser(macros: $this)->parse(self::MACROS[$name]) : null;
            }
        };
    }
}
