<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Parser\Lexer;
use Tilttek\Dice\Parser\Token;
use Tilttek\Dice\Parser\TokenType;

#[CoversClass(DiceParser::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(Token::class)]
#[CoversClass(InvalidExpression::class)]
final class ParserTest extends TestCase
{
    private const string NO_SIDES = 'A die needs its number of sides, "%", "F", a range between brackets, an expression between parentheses or a name between braces';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalForms(): iterable
    {
        yield 'upper case' => ['4D6KH3', '4d6kh3'];
        yield 'percentile' => ['d%', '1d100'];
        yield 'advantage' => ['d20 adv', '2d20kh1'];
        yield 'advantage glued' => ['1d20adv', '2d20kh1'];
        yield 'disadvantage in capitals' => ['D20DIS', '2d20kl1'];
        yield 'keep alone keeps the highest' => ['4d6k3', '4d6kh3'];
        yield 'keep lowest' => ['4d6kl1', '4d6kl1'];
        yield 'keep highest' => ['4d6kh1', '4d6kh1'];
        yield 'drop alone drops the lowest' => ['4d6d1', '4d6dl1'];
        yield 'drop highest' => ['4d6dh1', '4d6dh1'];
        yield 'drop lowest' => ['4d6DL1', '4d6dl1'];
        yield 'explosion' => ['3d6!', '3d6!'];
        yield 'explosion on a face' => ['3d6!6', '3d6!6'];
        yield 'explosion on an equal face' => ['3d6!=6', '3d6!6'];
        yield 'explosion above' => ['3d6!>5', '3d6!>5'];
        yield 'explosion from' => ['3d6!>=5', '3d6!>=5'];
        yield 'explosion below' => ['3d6!<2', '3d6!<2'];
        yield 'explosion up to' => ['3d6!<=2', '3d6!<=2'];
        yield 'fudge explosion on zero' => ['4dF!0', '4dF!0'];
        yield 'reroll' => ['4d6r1', '4d6r1'];
        yield 'reroll below' => ['4d6r<3', '4d6r<3'];
        yield 'reroll a negative face' => ['4dFr = -1', '4dFr=-1'];
        yield 'successes' => ['6d10>=7', '6d10>=7'];
        yield 'successes on a face' => ['6d10=10', '6d10=10'];
        yield 'successes above a negative face' => ['4dF>-1', '4dF>-1'];
        yield 'modifiers in the order they apply' => ['4d6kh3>=4!r1', '4d6r1!kh3>=4'];
        yield 'constant' => ['2d6+3', '2d6+3'];
        yield 'leading zeros' => ['007', '7'];
        yield 'leading zeros past ten digits' => ['00000000001', '1'];
        yield 'the largest number' => ['1000000000', '1000000000'];
        yield 'parentheses kept' => ['(1d8+2)*2', '(1d8+2)*2'];
        yield 'parentheses dropped' => ['((2))', '2'];
        yield 'subtraction is left-associative' => ['1-2-3', '1-2-3'];
        yield 'a right operand in parentheses' => ['1-(2-3)', '1-(2-3)'];
        yield 'division then multiplication' => ['10/3*2', '10/3*2'];
        yield 'division of a product' => ['10/(3*2)', '10/(3*2)'];
        yield 'negation' => ['-1d4', '-1d4'];
        yield 'negation of a sum' => ['-(1+2)', '-(1+2)'];
        yield 'double negation' => ['1--2', '1--2'];
        yield 'implicit count' => ['1d6+d4', '1d6+1d4'];
        yield 'spaces' => [' 4d6 dl1 + 2 ', '4d6dl1+2'];
        yield 'pool' => ['2d6,1d8 , 3', '2d6, 1d8, 3'];
        yield 'fudge' => ['4df', '4dF'];
        yield 'custom die' => ['3d{Chromatic}', '3d{chromatic}'];
        yield 'custom die with spaces' => ['d{ star }', '1d{star}'];
        yield 'two custom dice' => ['1d{a}+2d{bb}', '1d{a}+2d{bb}'];
        yield 'a die of one side' => ['d1', '1d1'];
        yield 'one side between parentheses' => ['3d(1)', '3d1'];
        yield 'a die on a range' => ['4d[-1..1]', '4d[-1..1]'];
        yield 'a die on a range from zero' => ['3d[ 0 .. 9 ]', '3d[0..9]'];
        yield 'a count rolled first' => ['(1d4+1)d6', '(1d4+1)d6'];
        yield 'sides rolled first' => ['3d(2*6)', '3d(2*6)'];
        yield 'a constant count between parentheses' => ['(3)d6', '3d6'];
        yield 'constant sides between parentheses' => ['3d(6)', '3d6'];
        yield 'a count rolled first, of Fudge dice' => ['(1d4)dF', '(1d4)dF'];
        yield 'a negative count rolled first' => ['(-1)d6', '(-1)d6'];
        yield 'weighted fudge' => ['4dF.1', '4dF.1'];
        yield 'fudge of two of each' => ['4dF.2', '4dF'];
        yield 'a list of values' => ['[10, 25, 43, 8]kh1', '[10,25,43,8]kh1'];
        yield 'a list of negative values' => ['[-1,3]', '[-1,3]'];
        yield 'a floor and a ceiling' => ['4d6max5min2', '4d6min2max5'];
        yield 'a negative floor' => ['4dFmin-1', '4dFmin-1'];
        yield 'a compound explosion' => ['3d10!!', '3d10!!'];
        yield 'a compound explosion on a face' => ['3d10!!=9', '3d10!!9'];
        yield 'a penetrating explosion' => ['2d6!p', '2d6!p'];
        yield 'a compound penetrating explosion' => ['2d6!!p>5', '2d6!!p>5'];
        yield 'an explosion limited' => ['3d10!(3)', '3d10!(3)'];
        yield 'an explosion limited by a roll' => ['3d10!>8(1d4)', '3d10!>8(1d4)'];
        yield 'an explosion on a validator' => ['4d6![!=6]', '4d6![!=6]'];
        yield 'a reroll once' => ['4d6ro1', '4d6ro1'];
        yield 'a reroll on a validator' => ['4d6r[1|2]', '4d6r[1|2]'];
        yield 'a reroll and add' => ['3d10a10', '3d10!!10(1)'];
        yield 'unique dice' => ['4d6u', '4d6u'];
        yield 'unique dice once' => ['4d6uo', '4d6uo'];
        yield 'unique dice then occurrences' => ['4d6uo2', '4d6uo2'];
        yield 'unique dice then occurrences of a height' => ['4d6uo[6]', '4d6uo2[6]'];
        yield 'unique dice once then occurrences' => ['4d6uoo3', '4d6uoo3'];
        yield 'dice all the same explode' => ['2d6t', '2d6t'];
        yield 'dice all the same, transformed' => ['2d6t[1:0]t', '2d6tt[1:0]'];
        yield 'a filter' => ['4d6f[!=6]', '4d6f[!=6]'];
        yield 'a filter of all the dice' => ['4d6f[all:>1]', '4d6f[all:>1]'];
        yield 'a sort' => ['4d6s', '4d6sa'];
        yield 'a sort ascending' => ['4d6SA', '4d6sa'];
        yield 'a sort descending' => ['4d6sd', '4d6sd'];
        yield 'failures' => ['6d10>=7f1', '6d10>=7f1'];
        yield 'failures below' => ['6d10f<3>=7', '6d10>=7f<3'];
        yield 'failures on a negative face' => ['4dF=1f=-1', '4dF=1f=-1'];
        yield 'a count' => ['8d10c[>=6]', '8d10>=6'];
        yield 'a count on a validator' => ['8d10c[6..10]', '8d10c[6..10]'];
        yield 'a count of a remainder' => ['8d10c[%2=0]', '8d10c[%2=0]'];
        yield 'a count of a remainder unlike' => ['8d10c[%3!=0]', '8d10c[%3!=0]'];
        yield 'a count of each die as it fell' => ['8d10c[die:10]', '8d10c[die:10]'];
        yield 'a count of any die' => ['8d10c[any:10]', '8d10c[any:10]'];
        yield 'a count of the total' => ['8d10c[total:>=40]', '8d10c[total:>=40]'];
        yield 'a count of a composite' => ['8d10c[ >2 & <5 | 10 ]', '8d10c[>2&<5|10]'];
        yield 'a count of a composite in parentheses' => ['8d10c[>2&(<5|10)]', '8d10c[>2&(<5|10)]'];
        yield 'a count of an exclusive composite' => ['8d10c[1^(2^3)]', '8d10c[1^(2^3)]'];
        yield 'a count of exclusive composites, left to right' => ['8d10c[(1^2)^3]', '8d10c[1^2^3]'];
        yield 'a count of a negative value' => ['4dFc[-1]', '4dF=-1'];
        yield 'a count of a value unlike' => ['4d6c[!=1]', '4d6c[!=1]'];
        yield 'the dice before, counted again' => ['8d10c[>=6]-@c[=1]', '8d10>=6-@=1'];
        yield 'the dice before, alone' => ['1d6+@', '1d6+@'];
        yield 'the dice of a list before' => ['[1,2]+@kh1', '[1,2]+@kh1'];
        yield 'occurrences' => ['10d10o', '10d10o2'];
        yield 'occurrences of a height' => ['10d10o3[>=7]', '10d10o3[>=7]'];
        yield 'groups' => ['5d10g10', '5d10g10'];
        yield 'a transformation' => ['5d10t[>5:15, <=5:0]', '5d10t[>5:15,<=5:0]'];
        yield 'a transformation of a die as it fell' => ['5d10t[die:1:-1]', '5d10t[die:1:-1]'];
        yield 'critical marks' => ['1d20cs>19cf1', '1d20cs>19cf1'];
        yield 'critical marks on the faces' => ['1d20CFCS', '1d20cscf'];
        yield 'critical marks on a validator' => ['1d20cs[19|20]', '1d20cs[19|20]'];
        yield 'look tokens' => ['8d10look[2:Accent, muted-2]', '8d10look[2:accent,muted-2]'];
        yield 'look tokens with counts' => ['8d10look[2:a,3:b]', '8d10look[2:a,3:b]'];
        yield 'every modifier in its order' => ['2d6sdlook[x]cscf1g8f[1..5]max5min2t[1:2]!p(2)uoro1', '2d6ro1uo!p(2)min2max5t[1:2]f[1..5]g8cscf1look[x]sd'];
        yield 'functions' => ['floor(1d6/3)+CEIL(1d6/3)+round(7/2)', 'floor(1d6/3)+ceil(1d6/3)+round(7/2)'];
        yield 'absolute value' => ['abs(1-1d6)', 'abs(1-1d6)'];
        yield 'smallest and largest' => ['min(1d6,1d8 ,3)+max(1d20,1d20)', 'min(1d6, 1d8, 3)+max(1d20, 1d20)'];
        yield 'remainder' => ['10%3', '10%3'];
        yield 'remainder of a product' => ['10%(3*2)', '10%(3*2)'];
        yield 'remainder then product' => ['10%3*2', '10%3*2'];
        yield 'power' => ['2^10', '2^10'];
        yield 'a negative exponent' => ['2^-1', '2^-1'];
        yield 'a power binds tighter than a negation' => ['-2^2', '-2^2'];
        yield 'a negated base' => ['(-2)^2', '(-2)^2'];
        yield 'powers from the right' => ['2^3^2', '2^3^2'];
        yield 'powers from the left' => ['(2^3)^2', '(2^3)^2'];
        yield 'a power of a product' => ['2^(3*2)', '2^(3*2)'];
        yield 'a product of powers' => ['2^3*2', '2^3*2'];
        yield 'a power of dice' => ['1d6^2', '1d6^2'];
    }

    #[DataProvider('canonicalForms')]
    public function testAnInputParsesToItsCanonicalForm(string $input, string $canonical): void
    {
        self::assertSame($canonical, new DiceParser()->parse($input)->canonical());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'no sides' => ['2d', 2, self::NO_SIDES];
        yield 'a letter for sides' => ['2dx', 2, self::NO_SIDES];
        yield 'fudge letter apart' => ['d F', 2, self::NO_SIDES];
        yield 'no side' => ['d0', 1, 'A die has at least one side'];
        yield 'no die' => ['0d6', 0, 'An expression rolls at least one die of each kind it names'];
        yield 'keep too many' => ['2d6kh3', 0, '2 dice cannot keep 3, nor drop all of them'];
        yield 'drop them all' => ['2d6dl2', 0, '2 dice cannot keep 2, nor drop all of them'];
        yield 'keep none' => ['2d6kh0', 5, 'A selection keeps or drops at least one die'];
        yield 'endless explosion' => ['d6!>=1', 0, 'Every face would explode: the roll would never end'];
        yield 'selection twice' => ['4d6kh3kh2', 6, 'A modifier is given twice'];
        yield 'reroll twice' => ['4d6r1r2', 5, 'A modifier is given twice'];
        yield 'explosion twice' => ['3d6!3!', 5, 'A modifier is given twice'];
        yield 'explosion and reroll and add' => ['3d6!a6', 4, 'A modifier is given twice'];
        yield 'explosion signs apart' => ['3d6! !', 5, 'A modifier is given twice'];
        yield 'reroll and add without a face' => ['3d6a', 4, 'Unexpected end'];
        yield 'an explosion limited to none' => ['3d6!(0)', 4, 'An explosion is limited to at least one per die; 0 is not'];
        yield 'an endless compound explosion' => ['d6!!>=1', 0, 'Every face would explode: the roll would never end'];
        yield 'an endless repeated reroll' => ['d6r<7', 0, 'Every face would be rerolled: the roll would never end'];
        yield 'unique dice past the faces' => ['7d6u', 0, '7 dice cannot all differ with 6 faces'];
        yield 'one die all the same' => ['1d6t', 0, 'Dice all the same explode from two dice: one die is always the same as itself'];
        yield 'all the same twice' => ['2d6tt', 4, 'A modifier is given twice'];
        yield 'a transformation of the total' => ['2d6t[total:>5:1]', 5, 'A transformation judges each die; the scope "total" judges them together'];
        yield 'a transformation without a value' => ['2d6t[1]', 6, 'Unexpected "]"'];
        yield 'failures without successes' => ['6d10f1', 0, 'Failures take a success away: they need successes judged die by die'];
        yield 'failures of the total' => ['6d10c[total:>5]f1', 0, 'Failures take a success away: they need successes judged die by die'];
        yield 'failures without a face' => ['6d10>5f', 7, 'Unexpected end'];
        yield 'successes and groups' => ['6d10>5g10', 0, 'A set of dice counts successes, occurrences or groups: one of them at most'];
        yield 'occurrences and groups' => ['6d10o2g10', 0, 'A set of dice counts successes, occurrences or groups: one of them at most'];
        yield 'a floor above the ceiling' => ['4d6min5max2', 0, 'The floor of a die, 5, is above its ceiling, 2'];
        yield 'floor without a value' => ['4d6min', 6, 'Unexpected end'];
        yield 'groups of nothing' => ['4d6g0', 0, 'Groups reach a sum of at least 1; 0 is not'];
        yield 'groups without a sum' => ['4d6g', 4, 'Unexpected end'];
        yield 'occurrences of no die' => ['4d6o0', 3, 'A set of occurrences holds at least one die; 0 is not'];
        yield 'a count without brackets' => ['4d6c6', 4, 'Unexpected "6"'];
        yield 'a count unclosed' => ['4d6c[6', 6, 'Unexpected end'];
        yield 'a validator without a value' => ['4d6c[]', 5, 'Unexpected "]"'];
        yield 'a validator not equal without its sign' => ['4d6c[!6]', 6, 'Unexpected "6"'];
        yield 'a range upside down' => ['4d6c[5..2]', 5, 'A range goes from its lower bound to its higher one; 5..2 does not'];
        yield 'a range with one dot' => ['4d6c[2.5]', 7, 'Unexpected "5"'];
        yield 'a remainder by zero' => ['4d6c[%0=0]', 5, 'A remainder is taken by a divisor of at least 1; 0 is not'];
        yield 'a remainder without a comparison' => ['4d6c[%2]', 7, 'Unexpected "]"'];
        yield 'a validator unclosed parenthesis' => ['4d6c[(1|2]', 9, 'Unexpected "]"'];
        yield 'a scope without a colon' => ['4d6c[all>2]', 5, 'Unexpected "a"'];
        yield 'too deep a validator' => ['4d6c['.str_repeat('(', 33).'1'.str_repeat(')', 33).']', 37, 'Too deeply nested'];
        yield 'a look token without a token' => ['4d6look[2:]', 10, 'Unexpected "]"'];
        yield 'a look token that is a number' => ['4d6look[2]', 9, 'Unexpected "]"'];
        yield 'a look token too long' => ['4d6look['.str_repeat('a', 33).']', 8, 'A look token is 1 to 32 lower-case letters, digits or "-", starting with a letter; "'.str_repeat('a', 33).'" is not'];
        yield 'a look token for no die' => ['4d6look[0:a]', 8, 'A look token is given to at least one die; 0 is not'];
        yield 'a look token for every die, then another' => ['4d6look[a,b]', 0, 'Only the last look token goes to every die left; the others give their count'];
        yield 'look without brackets' => ['4d6look', 3, 'Unexpected "l"'];
        yield 'a range die upside down' => ['4d[1..-1]', 2, 'A die goes from its lowest face to its highest; 1..-1 does not'];
        yield 'a range die unclosed' => ['4d[1..6', 7, 'Unexpected end'];
        yield 'a fudge die of three' => ['4dF.3', 4, 'A Fudge die is dF.1 or dF.2; dF.3 is not'];
        yield 'a fudge die without its variant' => ['4dF.', 4, 'Unexpected end'];
        yield 'sides of none between parentheses' => ['3d(0)', 1, 'A die has at least one side'];
        yield 'sides rolled first, unclosed' => ['3d(6', 4, 'Unexpected end'];
        yield 'a list without values' => ['[]', 1, 'Unexpected "]"'];
        yield 'a list unclosed' => ['[1,2', 4, 'Unexpected end'];
        yield 'a list that explodes' => ['[1,2]!', 0, 'A list of values is not rolled: it takes no reroll and no explosion'];
        yield 'a list with advantage' => ['[1,2]adv', 6, 'Unexpected "d"'];
        yield 'a list keeping too many' => ['[1,2]kh3', 0, '2 dice cannot keep 3, nor drop all of them'];
        yield 'a list with a modifier twice' => ['[1,2]kh1kh1', 8, 'A modifier is given twice'];
        yield 'the dice before, with nothing before' => ['@=1', 0, '"@" judges the dice of a set before it, and none comes before'];
        yield 'the dice before, in another expression' => ['1d6, @=1', 5, '"@" judges the dice of a set before it, and none comes before'];
        yield 'the dice before, with advantage' => ['1d6+@adv', 6, 'Unexpected "d"'];
        yield 'the dice before, rerolled' => ['1d6+@r1', 4, 'The dice of the set before are not rolled again: "@" takes no reroll and no explosion'];
        yield 'a function without an argument' => ['abs()', 4, 'Unexpected ")"'];
        yield 'a function of two arguments' => ['abs(1,2)', 0, 'abs() takes one argument; 2 given'];
        yield 'min of one argument' => ['min(1)', 0, 'min() takes two arguments or more; 1 given'];
        yield 'a function unclosed' => ['max(1,2', 7, 'Unexpected end'];
        yield 'a function name apart' => ['ab s(1)', 0, 'Unexpected "a"'];
        yield 'a function without parentheses' => ['abs 1', 0, 'Unexpected "a"'];
        yield 'too many functions' => [str_repeat('abs(', 33).'1'.str_repeat(')', 33), 131, 'Too deeply nested'];
        yield 'a power without exponent' => ['2^', 2, 'Unexpected end'];
        yield 'a count rolled first, with advantage' => ['(1d4)d20 adv', 0, 'Advantage and disadvantage roll one die twice, and select it themselves'];
        yield 'a count rolled first, invalid' => ['(1d4)d{x}!', 0, 'A custom die takes no modifier'];
        yield 'successes twice' => ['6d10>5>6', 6, 'A modifier is given twice'];
        yield 'advantage twice' => ['d20 adv adv', 8, 'A modifier is given twice'];
        yield 'advantage of two dice' => ['2d20 dis', 0, 'Advantage and disadvantage roll one die twice, and select it themselves'];
        yield 'advantage with a selection' => ['1d20kh1 adv', 0, 'Advantage and disadvantage roll one die twice, and select it themselves'];
        yield 'advantage spelled apart' => ['d20 a dv', 6, 'Unexpected "d"'];
        yield 'advantage cut short' => ['d20 ad', 5, 'Unexpected "d"'];
        yield 'unknown modifier' => ['2d6x', 3, 'Unexpected "x"'];
        yield 'drop without a count' => ['4d6d', 3, 'Unexpected "d"'];
        yield 'drop followed by a letter' => ['4d6dx', 3, 'Unexpected "d"'];
        yield 'drop highest without a count' => ['4d6dh', 5, 'Unexpected end'];
        yield 'keep without a count' => ['4d6k', 4, 'Unexpected end'];
        yield 'reroll without a face' => ['4d6r', 4, 'Unexpected end'];
        yield 'reroll on a letter' => ['4d6rx', 4, 'Unexpected "x"'];
        yield 'comparison without a face' => ['4d6>=', 5, 'Unexpected end'];
        yield 'unclosed parenthesis' => ['((1)', 4, 'Unexpected end'];
        yield 'two numbers' => ['2 3', 2, 'Unexpected "3"'];
        yield 'nothing' => ['', 0, 'Unexpected end'];
        yield 'a word' => ['adv', 0, 'Unexpected "a"'];
        yield 'an unknown sign' => ['2d6~', 3, 'Unexpected "~"'];
        yield 'a letter outside ASCII, by character' => ['2d6 é', 4, 'Unexpected "é"'];
        yield 'unclosed brace' => ['d{x', 1, 'Unclosed brace'];
        yield 'a brace apart from its die' => ['d {x}', 2, self::NO_SIDES];
        yield 'invalid die name' => ['3d{Bad!}', 2, 'A die name is 1 to 32 lower-case letters, digits, "_" or "-", starting with a letter; "bad!" is not'];
        yield 'custom die with a modifier' => ['3d{x}!', 0, 'A custom die takes no modifier'];
        yield 'a number too large' => ['1000000001', 0, 'A number is at most 1000000000'];
        yield 'a number past integers' => ['99999999999999999999', 0, 'A number is at most 1000000000'];
        yield 'too many parentheses' => [str_repeat('(', 33).'1'.str_repeat(')', 33), 32, 'Too deeply nested'];
        yield 'too many negations' => [str_repeat('-', 33).'1', 32, 'Too deeply nested'];
    }

    #[DataProvider('invalidInputs')]
    public function testAnInvalidInputIsRefusedWithItsPosition(string $input, int $position, string $reason): void
    {
        try {
            new DiceParser()->parse($input);
            self::fail('The input parsed.');
        } catch (InvalidExpression $invalidExpression) {
            self::assertSame([\sprintf('%s at position %d of "%s".', $reason, $position, $input), $input, $position], [$invalidExpression->getMessage(), $invalidExpression->input, $invalidExpression->position]);
        }
    }

    public function testNestingStopsAtItsDepthAndRecovers(): void
    {
        $parser = new DiceParser();

        $long = new DiceParser(new Limits(length: 1000));
        self::assertSame(str_repeat('-1+', 40).'1', $long->parse(str_repeat('-1+', 40).'1')->canonical());
        self::assertSame(str_repeat('abs(1)+', 40).'1', $long->parse(str_repeat('abs(1)+', 40).'1')->canonical());
        self::assertSame('4d6c['.str_repeat('1|', 40).'1]', $long->parse('4d6c['.str_repeat('(1)|', 40).'1]')->canonical());

        self::assertSame('1+1', $parser->parse(str_repeat('(', 32).'1'.str_repeat(')', 32).'+'.str_repeat('(', 32).'1'.str_repeat(')', 32))->canonical());
        self::assertSame(str_repeat('-', 32).'1', $parser->parse(str_repeat('-', 32).'1')->canonical());
        self::assertSame('2', $parser->parse('2')->canonical());
    }

    /**
     * @return iterable<string, array{Limits, string, string}>
     */
    public static function limitsPassed(): iterable
    {
        yield 'length' => [new Limits(length: 5), '1d6+12', 'The characters of an expression are limited to 5; this asks for 6.'];
        yield 'dice' => [new Limits(dice: 3), '2d6+2d6', 'The dice of a roll are limited to 3; this asks for 4.'];
        yield 'sides' => [new Limits(sides: 100), 'd101', 'The sides of a die are limited to 100; this asks for 101.'];
        yield 'expressions' => [new Limits(expressions: 2), '1, 2, 3', 'The expressions of a pool are limited to 2; this asks for 3.'];
    }

    #[DataProvider('limitsPassed')]
    public function testAnInputPastALimitIsRefused(Limits $limits, string $input, string $message): void
    {
        $this->expectExceptionObject(new LimitExceeded($message));

        new DiceParser($limits)->parse($input);
    }

    public function testTheLengthCountsCharacters(): void
    {
        $this->expectException(InvalidExpression::class);

        new DiceParser(new Limits(length: 1))->parse('é');
    }

    public function testANotationReadsItsLettersAndTheCanonicalOnes(): void
    {
        $parser = new DiceParser(notation: new Notation('W', 'VOR', 'nach'));

        self::assertSame('2d6, 2d6, 2d6, 2d20kh1, 2d20kl1, 4dF', $parser->parse('2W6, 2w6, 2d6, W20 vor, w20 nach, 4WF')->canonical());

        $this->expectException(InvalidExpression::class);
        $parser->parse('d20 adv');
    }

    public function testTokensKnowTheirKindAndText(): void
    {
        $token = new Token(TokenType::Letter, 'K', 3);

        self::assertTrue($token->is(TokenType::Letter));
        self::assertTrue($token->is(TokenType::Letter, 'K'));
        self::assertFalse($token->is(TokenType::Letter, 'k'));
        self::assertFalse($token->is(TokenType::Number));
        self::assertTrue($token->isLetter('k'));
        self::assertFalse($token->isLetter('d'));
        self::assertFalse(new Token(TokenType::Name, 'k', 0)->isLetter('k'));
    }

    public function testTheLexerReadsEveryKindOfToken(): void
    {
        self::assertSame(
            [['Number', '12', 0], ['Letter', 'd', 2], ['Name', 'a b', 3], ['Compare', '>=', 8], ['Compare', '<', 10], ['Compare', '=', 12], ['Compare', '>', 13], ['Symbol', '!', 15], ['Number', '2', 16], ['Number', '3', 18], ['Symbol', '%', 19], ['End', '', 20]],
            array_map(static fn (Token $token): array => [$token->type->name, $token->text, $token->position], Lexer::tokenize("12d{a b}>=< => !2\t3%")),
        );
        self::assertSame(['^', '[', ']', '&', '|', ':', '@', '.', ''], array_map(static fn (Token $token): string => $token->text, Lexer::tokenize('^[]&|:@.')));
    }

    public function testATokenKnowsWhetherItFollowsAnother(): void
    {
        $tokens = Lexer::tokenize('12d >=1');

        self::assertSame([true, false, true], [$tokens[1]->follows($tokens[0]), $tokens[2]->follows($tokens[1]), $tokens[3]->follows($tokens[2])]);
        self::assertFalse($tokens[0]->follows($tokens[1]));
    }
}
