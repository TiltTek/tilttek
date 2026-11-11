<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Dialect\Avrae;
use Tilttek\Dice\Dialect\Dialect;
use Tilttek\Dice\Dialect\Dialects;
use Tilttek\Dice\Dialect\FoundryVtt;
use Tilttek\Dice\Dialect\Roll20;
use Tilttek\Dice\Dialect\Translation;
use Tilttek\Dice\Dialect\Unsupported;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\UntranslatableExpression;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Parser\DiceParser;

#[CoversClass(Dialect::class)]
#[CoversClass(DiceRoller::class)]
#[CoversClass(Roll20::class)]
#[CoversClass(FoundryVtt::class)]
#[CoversClass(Avrae::class)]
#[CoversClass(Dialects::class)]
#[CoversClass(Translation::class)]
#[CoversClass(Unsupported::class)]
#[CoversClass(UntranslatableExpression::class)]
final class DialectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function expressions(): iterable
    {
        yield 'plain' => ['2d6+3', '2d6+3', '2d6+3', '2d6+3'];
        yield 'keep and drop' => ['4d6kh3+4d6kl1-4d6dh1*4d6dl1', '4d6kh3+4d6kl1-4d6dh1*4d6dl1', '4d6kh3+4d6kl1-4d6dh1*4d6dl1', '4d6kh3+4d6kl1-4d6ph1*4d6pl1'];
        yield 'reroll a face' => ['4d6ro1', '4d6ro1', '4d6r=1', '4d6ro1'];
        yield 'reroll below' => ['4d6ro<3', '4d6ro<2', '4d6r<3', '4d6ro<3'];
        yield 'reroll up to' => ['4d6ro<=2', '4d6ro<2', '4d6r<=2', '4d6ro<3'];
        yield 'reroll above' => ['4d6ro>5', '4d6ro>6', '4d6r>5', '4d6ro>5'];
        yield 'reroll from' => ['4d6ro>=5', '4d6ro>5', '4d6r>=5', '4d6ro>4'];
        yield 'reroll repeated' => ['4d6r1', '4d6r1', '4d6rr=1', '4d6rr1'];
        yield 'reroll repeated below' => ['4d6r<3', '4d6r<2', '4d6rr<3', '4d6rr<3'];
        yield 'floor and ceiling' => ['4d6min2max5+1', '✗ 4d6min2max5, 4d6min2max5', '4d6min2max5+1', '4d6mi2ma5+1'];
        yield 'explosion once' => ['3d10!>8(1)', '✗ 3d10!>8(1)', '3d10xo>8', '✗ 3d10!>8(1)'];
        yield 'a list of values' => ['[10,25,43,8]kh1', '{10,25,43,8}kh1', '{10,25,43,8}kh1', '(10, 25, 43, 8)kh1'];
        yield 'a list of values dropped' => ['[10,-25]dl1', '{10,-25}dl1', '{10,-25}dl1', '(10, -25)pl1'];
        yield 'floor' => ['floor(1d6/3)', 'floor(1d6/3)', 'floor(1d6/3)', '(1d6//3)'];
        yield 'floor of a sum' => ['floor(1d4)+1', 'floor(1d4)+1', 'floor(1d4)+1', '(1d4)+1'];
        yield 'ceiling' => ['ceil(1d6/3)', 'ceil(1d6/3)', 'ceil(1d6/3)', '✗ 1d6/3'];
        yield 'ceiling of a sum' => ['ceil(1d6/3+1)', 'ceil(ceil(1d6/3)+1)', 'ceil(ceil(1d6/3)+1)', '✗ 1d6/3'];
        yield 'rounding' => ['round(1d6/3)', 'round(1d6/3)', 'round(1d6/3)', '✗ 1d6/3'];
        yield 'rounding without division' => ['round(1d6-4)', 'round(1d6-4)', 'round(1d6-4)', '(1d6-4)'];
        yield 'rounding of a remainder and a power' => ['round((1d6%3+2^2)/2)', 'round((1d6%3+(2**2))/2)', 'round((1d6%3+(2**2))/2)', '✗ 2^2, (1d6%3+2^2)/2'];
        yield 'absolute value' => ['abs(1d6-4)', 'abs(1d6-4)', 'abs(1d6-4)', '✗ abs(1d6-4)'];
        yield 'remainder' => ['1d20%2', '1d20%2', '1d20%2', '1d20%2'];
        yield 'remainder of an absolute value' => ['abs(1d20-5)%2', 'abs(1d20-5)%2', 'abs(1d20-5)%2', '✗ abs(1d20-5)'];
        yield 'remainder of a quotient' => ['1d20/2*3%2', 'floor(1d20/2)*3%2', 'floor(1d20/2)*3%2', '1d20//2*3%2'];
        yield 'remainder of a difference' => ['(1d6-3)%2', '((1d6-3)%2+2)%2', '((1d6-3)%2+2)%2', '(1d6-3)%2'];
        yield 'remainder of a negation' => ['-1d6%2', '(-1d6%2+2)%2', '(-1d6%2+2)%2', '-1d6%2'];
        yield 'power' => ['2^3', '(2**3)', '(2**3)', '✗ 2^3'];
        yield 'failures' => ['6d10>=7f1', '6d10>7f1', '6d10cs>=7df=1', '✗ 6d10>=7f1'];
        yield 'failures below' => ['6d10>=7f<3', '6d10>7f<2', '6d10cs>=7df<3', '✗ 6d10>=7f<3'];
        yield 'critical marks' => ['1d20cs>19cf<=2', '1d20cs>20cf<2', '✗ 1d20cs>19cf<=2', '✗ 1d20cs>19cf<=2'];
        yield 'critical marks on the faces' => ['1d20cscf', '1d20cs20cf1', '✗ 1d20cscf', '✗ 1d20cscf'];
        yield 'critical marks on a face' => ['1d20cs20', '1d20cs20', '✗ 1d20cs20', '✗ 1d20cs20'];
        yield 'a sort' => ['4d6sd+4d6sa', '4d6sd+4d6sa', '✗ 4d6sd, 4d6sa', '✗ 4d6sd, 4d6sa'];
        yield 'a list with successes and failures' => ['[10,8]>=9f8', '{10,8}>9f8', '{10,8}cs>=9df=8', '✗ [10,8]>=9f8'];
        yield 'smallest and largest' => ['min(1d6, 3)+max(1d6, 3)', '✗ min(1d6, 3), max(1d6, 3)', 'min(1d6, 3)+max(1d6, 3)', '✗ min(1d6, 3), max(1d6, 3)'];
        yield 'explosion on the highest face of a d10' => ['3d10!+1d4!', '3d10!+1d4!', '3d10x+1d4x', '3d10e10+1d4e4'];
        yield 'explosion above' => ['3d10!>8', '3d10!>9', '3d10x>8', '3d10e>8'];
        yield 'power of dice' => ['2^1d4', '(2**1d4)', '(2**1d4)', '✗ 2^1d4'];
        yield 'explosion' => ['3d6!', '3d6!', '3d6x', '3d6e6'];
        yield 'explosion of a d10' => ['3d10!', '3d10!', '3d10x', '3d10e10'];
        yield 'explosion on a face' => ['3d6!5', '3d6!5', '3d6x=5', '3d6e5'];
        yield 'explosion from' => ['3d6!>=5', '3d6!>5', '3d6x>=5', '3d6e>4'];
        yield 'division' => ['10/3', 'floor(10/3)', 'floor(10/3)', '10//3'];
        yield 'division of a sum' => ['(1d6+4)/2', 'floor((1d6+4)/2)', 'floor((1d6+4)/2)', '(1d6+4)//2'];
        yield 'division then product' => ['10/3*2', 'floor(10/3)*2', 'floor(10/3)*2', '10//3*2'];
        yield 'product of a division' => ['2*(10/3)', '2*floor(10/3)', '2*floor(10/3)', '2*(10//3)'];
    }

    /**
     * Each dialect's text, or the constructs of the library's notation it reports, marked `✗`.
     */
    #[DataProvider('expressions')]
    public function testEachDialectWritesTheExpressionItsWay(string $expression, string $roll20, string $foundry, string $avrae): void
    {
        $pool = new DiceParser()->parse($expression);

        self::assertSame([$roll20, $foundry, $avrae], array_map(static fn (Dialect $dialect): string => self::written($dialect->translate($pool)), [new Roll20(), new FoundryVtt(), new Avrae()]));
    }

    /**
     * The text of a complete translation, or the constructs it reports, marked.
     */
    private static function written(Translation $translation): string
    {
        if ($translation->isComplete()) {
            return $translation->expressions[0];
        }

        return '✗ '.implode(', ', array_map(static fn (Unsupported $unsupported): string => $unsupported->construct, $translation->unsupported));
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, list<string>}>
     */
    public static function unwritten(): iterable
    {
        yield 'a reroll on a validator' => ['4d6r[1|2]', ['a reroll compares with one point'], ['a reroll compares with one threshold'], ['a reroll selects with one comparison']];
        yield 'a compound explosion' => ['3d10!!', [], ['an explosion neither compounds nor penetrates'], ['an explosion neither compounds nor penetrates']];
        yield 'a penetrating explosion' => ['2d6!p', [], ['an explosion neither compounds nor penetrates'], ['an explosion neither compounds nor penetrates']];
        yield 'a compound penetrating explosion' => ['2d6!!p', ['a penetrating explosion does not compound'], ['an explosion neither compounds nor penetrates'], ['an explosion neither compounds nor penetrates']];
        yield 'a limit of explosions' => ['3d10!(3)', ['a limit of explosions is not written'], ['an explosion is limited to once at most'], ['a limit of explosions is not written']];
        yield 'a limit of explosions rolled' => ['3d10!(1d4)', ['a limit of explosions is not written'], ['an explosion is limited to once at most'], ['a limit of explosions is not written']];
        yield 'an explosion on a validator' => ['3d10![!=1]', ['an explosion compares with one point'], ['an explosion compares with one threshold'], ['an explosion selects with one comparison']];
        yield 'a floor and a ceiling' => ['4d6min2max5', ['a floor is not written', 'a ceiling is not written'], [], []];
        yield 'successes of a die as it fell' => ['6d10c[die:7]', ['successes compare each final value with one point'], ['successes compare each final value with one threshold'], ['a count of successes is not written']];
        yield 'successes of a validator' => ['6d10c[7..8]', ['successes compare each final value with one point'], ['successes compare each final value with one threshold'], ['a count of successes is not written']];
        yield 'critical marks' => ['1d20cs>19cf1', [], ['a critical mark is not written'], ['a critical mark is not written']];
        yield 'a critical mark on a validator' => ['1d20cs[19|20]', ['a critical mark compares with one point'], ['a critical mark is not written'], ['a critical mark is not written']];
        yield 'a sort' => ['4d6sd', [], ['a sort is not written'], ['a sort is not written']];
        yield 'unique dice' => ['4d6u', ['a unique reroll is not written'], ['a unique reroll is not written'], ['a unique reroll is not written']];
        yield 'dice all the same' => ['2d6t', ['an explosion of dice all the same is not written'], ['an explosion of dice all the same is not written'], ['an explosion of dice all the same is not written']];
        yield 'a transformation' => ['4d6t[1:0]', ['a transformation is not written'], ['a transformation is not written'], ['a transformation is not written']];
        yield 'a filter' => ['4d6f[>1]', ['a filter is not written'], ['a filter is not written'], ['a filter is not written']];
        yield 'occurrences' => ['10d10o2', ['a count of occurrences is not written'], ['a count of occurrences is not written'], ['a count of occurrences is not written']];
        yield 'groups' => ['5d10g10', ['a count of groups is not written'], ['a count of groups is not written'], ['a count of groups is not written']];
        yield 'a look token' => ['8d10look[accent]', ['a look token is not written'], ['a look token is not written'], ['a look token is not written']];
        yield 'a list with every modifier no table writes' => ['[1,2]min1max2t[1:0]f[>0]o1cscflook[a]sa', ['a floor is not written', 'a ceiling is not written', 'a transformation is not written', 'a filter is not written', 'a count of occurrences is not written', 'a critical mark is not written', 'a look token is not written', 'a sort is not written'], ['a floor is not written', 'a ceiling is not written', 'a transformation is not written', 'a filter is not written', 'a count of occurrences is not written', 'a critical mark is not written', 'a look token is not written', 'a sort is not written'], ['a floor of a set is not written', 'a ceiling of a set is not written', 'a transformation is not written', 'a filter is not written', 'a count of occurrences is not written', 'a critical mark is not written', 'a look token is not written', 'a sort is not written']];
        yield 'a list with groups' => ['[1,2]g2', ['a count of groups is not written'], ['a count of groups is not written'], ['a count of groups is not written']];
        yield 'a list with a critical failure' => ['[1,2]cf', ['a critical mark is not written'], ['a critical mark is not written'], ['a critical mark is not written']];
        yield 'a list with successes' => ['[1,2]>=2f1', [], [], ['a count of successes is not written']];
        yield 'a list with successes of a validator' => ['[1,2]c[1|2]', ['successes compare each final value with one point'], ['successes compare each final value with one threshold'], ['a count of successes is not written']];
        yield 'weighted Fudge dice' => ['4dF.1', ['weighted Fudge dice are not written'], ['weighted Fudge dice are not written'], ['Avrae has no Fudge dice']];
        yield 'a die on a range' => ['4d[0..9]', ['a die on a range is not written'], ['a die on a range is not written'], ['a die on a range is not written']];
        yield 'a count rolled first' => ['(1d4)d6', ['a count or a number of sides rolled first is not written'], ['a count or a number of sides rolled first is not written'], ['a count or a number of sides rolled first is not written']];
        yield 'the dice before' => ['1d6+@=1', ['the dice of the set before are not judged again'], ['the dice of the set before are not judged again'], ['the dice of the set before are not judged again']];
        yield 'smallest and largest' => ['min(1d6, 3)+max(1d6, 3)', ['min() is not written', 'max() is not written'], [], ['min() is not written', 'max() is not written']];
        yield 'absolute value' => ['abs(-1)', [], [], ['abs() is not written']];
        yield 'a division inside ceil()' => ['ceil(1d6/3)', [], [], ['a division inside ceil() is not written']];
        yield 'a division inside round()' => ['round(1d6/3)', [], [], ['a division inside round() is not written']];
        yield 'a division of a negative value inside round()' => ['round((1d6-4)/2)', ['round() rounds the negative halves up'], ['round() rounds the negative halves up'], ['a division inside round() is not written']];
        yield 'a division by a negative value inside round()' => ['round(1d6/-2)', ['round() rounds the negative halves up'], ['round() rounds the negative halves up'], ['a division inside round() is not written']];
        yield 'a power' => ['2^3', [], [], ['a power is not written']];
        yield 'a power to a negative exponent' => ['2^(1d4-2)', ['a power whose exponent may be negative is not written'], ['a power whose exponent may be negative is not written'], ['a power is not written']];
        yield 'a remainder by a negative value' => ['1d6%(1d4-2)', ['a remainder of a negative value has the sign of the dividend'], ['a remainder of a negative value has the sign of the dividend'], []];
    }

    /**
     * @param list<string> $roll20
     * @param list<string> $foundry
     * @param list<string> $avrae
     */
    #[DataProvider('unwritten')]
    public function testEachDialectReportsWhatItCannotWrite(string $expression, array $roll20, array $foundry, array $avrae): void
    {
        $pool = new DiceParser()->parse($expression);

        self::assertSame(
            [$roll20, $foundry, $avrae],
            array_map(static fn (Dialect $dialect): array => array_map(static fn (Unsupported $unsupported): string => $unsupported->reason, $dialect->translate($pool)->unsupported), [new Roll20(), new FoundryVtt(), new Avrae()]),
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function signs(): iterable
    {
        yield 'a constant' => ['3', true];
        yield 'zero' => ['0', true];
        yield 'a negative constant' => ['0-3', false];
        yield 'dice' => ['1d6', true];
        yield 'Fudge dice' => ['1dF', false];
        yield 'dice with failures' => ['1d6>5f1', false];
        yield 'dice transformed' => ['1d6t[1:2]', false];
        yield 'dice with a floor' => ['1d6min0', true];
        yield 'dice with a negative floor' => ['1d6min-1', false];
        yield 'a sum' => ['1d6+1', true];
        yield 'a difference' => ['1d6-1', false];
        yield 'a product' => ['1d6*2', true];
        yield 'a product by a negative value' => ['1d6*(0-2)', false];
        yield 'a product of a negative value' => ['(0-2)*1d6', false];
        yield 'a quotient' => ['1d6/2', true];
        yield 'a remainder' => ['1d6%2', true];
        yield 'a power' => ['2^1d6', true];
        yield 'an absolute value' => ['abs(0-2)', true];
        yield 'a maximum' => ['max(1d6, 2)', true];
        yield 'a maximum of a negative value' => ['max(1d6, 0-2)', false];
        yield 'a maximum of a negative value first' => ['max(0-2, 1d6)', false];
        yield 'a negation' => ['-1', false];
        yield 'a list' => ['[1]', false];
    }

    /**
     * Whether a dialect writes a remainder plainly, which it does when neither operand can be negative: through a
     * second remainder when only the dividend may be, not at all when the divisor may be.
     */
    #[DataProvider('signs')]
    public function testARemainderIsPlainWhenNeitherOperandCanBeNegative(string $operand, bool $plain): void
    {
        $dividend = new FoundryVtt()->translate(new DiceParser()->parse('('.$operand.')%2'))->expressions[0];
        $divisor = new FoundryVtt()->translate(new DiceParser()->parse('1d6%('.$operand.')'));

        self::assertSame([$plain, $plain], [!str_contains($dividend, '+2)%2'), $divisor->isComplete()]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function successes(): iterable
    {
        yield 'from' => ['6d10>=7', '6d10>7', '6d10cs>=7'];
        yield 'above' => ['6d10>6', '6d10>7', '6d10cs>6'];
        yield 'a face' => ['6d10=10', '6d10=10', '6d10cs=10'];
        yield 'below' => ['6d10<3', '6d10<2', '6d10cs<3'];
        yield 'up to' => ['6d10<=2', '6d10<2', '6d10cs<=2'];
        yield 'fudge' => ['4dF>=1', '4dF>1', '4dFcs>=1'];
    }

    #[DataProvider('successes')]
    public function testSuccessesAreWrittenByRoll20AndFoundry(string $expression, string $roll20, string $foundry): void
    {
        $pool = new DiceParser()->parse($expression);

        self::assertSame([[$roll20], [$foundry]], [new Roll20()->translate($pool)->expressions, new FoundryVtt()->translate($pool)->expressions]);
    }

    public function testWhatADialectCannotWriteStandsAsTheLibraryWritesIt(): void
    {
        self::assertSame(['4d[0..9]', '4d{chromatic}', '4dF.1'], new Roll20()->translate(new DiceParser()->parse('4d[0..9], 4d{chromatic}, 4dF.1'))->expressions);
    }

    public function testAPoolIsOneCommandPerExpression(): void
    {
        $translation = new Roll20()->translate(new DiceParser()->parse('2d6, 1d8'));

        self::assertSame(['roll20', '/r ', ['2d6', '1d8'], []], [$translation->dialect, $translation->command, $translation->expressions, $translation->unsupported]);
        self::assertSame(['/r 2d6', '/r 1d8'], $translation->commands());
        self::assertSame("/r 2d6\n/r 1d8", $translation->text());
        self::assertTrue($translation->isComplete());
    }

    public function testWhatADialectCannotWriteIsReportedNotWrittenWrong(): void
    {
        $avrae = new Avrae()->translate(new DiceParser()->parse('4dF, 6d10>=7, 2d6, 3d{chromatic}'));

        self::assertFalse($avrae->isComplete());
        self::assertSame(
            [['4dF', 'Avrae has no Fudge dice'], ['6d10>=7', 'a count of successes is not written'], ['3d{chromatic}', 'custom dice are declared by each site']],
            array_map(static fn (Unsupported $unsupported): array => [$unsupported->construct, $unsupported->reason], $avrae->unsupported),
        );
        self::assertSame(['4dF', '6d10', '2d6', '3d{chromatic}'], $avrae->expressions);
        self::assertSame([['3d{chromatic}', 'custom dice are declared by each site']], array_map(static fn (Unsupported $unsupported): array => [$unsupported->construct, $unsupported->reason], new FoundryVtt()->translate(new DiceParser()->parse('3d{chromatic}'))->unsupported));

        $this->expectExceptionObject(new UntranslatableExpression('avrae cannot write 4dF, 6d10>=7, 3d{chromatic}.'));
        $avrae->text();
    }

    public function testATranslationStartsAfresh(): void
    {
        $avrae = new Avrae();
        $avrae->translate(new DiceParser()->parse('4dF'));

        self::assertTrue($avrae->translate(new DiceParser()->parse('4d6'))->isComplete());
        self::assertSame('/r 4dF', new Roll20()->translate(new DiceParser()->parse('4dF'))->text());
    }

    /**
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function instructions(): iterable
    {
        yield 'a reference' => ['1d6; $1+1', [['$1', 'the result of another instruction is not referred to']]];
        yield 'the dice of an instruction' => ['1d6; @1kh1', [['@1kh1', 'the dice of other instructions are not judged again']]];
        yield 'a merge' => ['1d6; m[1]', [['m[1]', 'the dice of other instructions are not judged again']]];
        yield 'a group' => ['{1d6, 1d8}kh1', [['{1d6, 1d8}kh1', 'a group of expressions is not written']]];
        yield 'a text list' => ['2L[a,b]', [['2L[a,b]', 'a text list is not written']]];
        yield 'a sentence' => ['"hit"', [['"hit"', 'a sentence is not written']]];
        yield 'an if' => ['1d20i[>=15]{1}{0}', [['1d20i[>=15]{1}{0}', 'an if is not written']]];
        yield 'an if on what a table cannot write' => ['1d{x}i[1]{1}', [['1d{x}', 'custom dice are declared by each site'], ['1d{x}i[1]{1}', 'an if is not written']]];
        yield 'a switch' => ['1d6S^[1]{1}{0}', [['1d6S^[1]{1}{0}', 'a switch is not written']]];
        yield 'repeat' => ['repeat(1d6, 2)', [['repeat(1d6, 2)', 'repeat() is not written']]];
    }

    /**
     * @param list<array{string, string}> $unsupported
     */
    #[DataProvider('instructions')]
    public function testTheConstructsOfInstructionsAreReported(string $expression, array $unsupported): void
    {
        $pool = new DiceParser()->parse($expression);

        foreach ([new Roll20(), new FoundryVtt(), new Avrae()] as $dialect) {
            $translation = $dialect->translate($pool);
            $reported = array_map(static fn (Unsupported $unsupported): array => [$unsupported->construct, $unsupported->reason], $translation->unsupported);

            self::assertSame($unsupported, \array_slice($reported, -\count($unsupported)), $dialect->name());
        }
    }

    public function testADivisionRoundedAnotherWayIsNoticed(): void
    {
        $pool = new DiceParser()->parse('1d6/2+1d6/2, 1d8/3, floor(1d6/2)+ceil(1d6/2), 7');
        $translation = new Roll20()->translate($pool, RoundingMode::HalfEven);

        self::assertSame(['1d6/2 is written rounded down; the roll rounds it HalfEven.', '1d8/3 is written rounded down; the roll rounds it HalfEven.'], $translation->notices);
        self::assertTrue($translation->isComplete());
        self::assertSame(['floor(1d6/2)+floor(1d6/2)', 'floor(1d8/3)', 'floor(1d6/2)+ceil(1d6/2)', '7'], $translation->expressions);
        self::assertSame([], new Roll20()->translate($pool)->notices);
        self::assertSame([], new Roll20()->translate($pool, RoundingMode::NegativeInfinity)->notices);
        self::assertSame([], new Avrae()->translate(new DiceParser()->parse('7'), RoundingMode::PositiveInfinity)->notices);
        self::assertSame(['1d6//2'], new Avrae()->translate(new DiceParser()->parse('1d6/2'), RoundingMode::PositiveInfinity)->expressions);

        $foundry = new FoundryVtt();
        $foundry->translate(new DiceParser()->parse('1d6/2'), RoundingMode::PositiveInfinity);
        self::assertSame([], $foundry->translate(new DiceParser()->parse('1d6/2'))->notices);
    }

    public function testDiceRollerTranslatesWithTheRoundingOfTheRoll(): void
    {
        $dice = new DiceRoller();

        self::assertSame(['1d6/2 is written rounded down; the roll rounds it PositiveInfinity.'], $dice->translate($dice->parse('1d6/2'), 'roll20', RoundingMode::PositiveInfinity)->notices);
        self::assertSame([], $dice->translate($dice->parse('1d6/2'), 'roll20')->notices);
    }

    public function testADieOfAnotherLibraryIsRefused(): void
    {
        $die = new class implements DieType {};

        $this->expectExceptionObject(InvalidDefinition::because(\sprintf('%s is not a die the library writes.', $die::class)));
        new Roll20()->translate(new Pool(new Dice(1, $die)));
    }

    public function testTheDialectsAreFoundByName(): void
    {
        $dialects = new Dialects();

        self::assertSame(['roll20', 'foundry', 'avrae', 'diceparser'], $dialects->names());
        self::assertInstanceOf(FoundryVtt::class, $dialects->get('foundry'));

        $mine = new class extends Dialect {
            #[Override]
            public function name(): string
            {
                return 'roll20';
            }

            #[Override]
            protected function command(): string
            {
                return '';
            }

            #[Override]
            protected function modifiers(Modifiers $modifiers, Node $node, ?NumericDie $die): string
            {
                return '';
            }

            #[Override]
            protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array
            {
                return ['', self::ATOM];
            }
        };

        self::assertSame($mine, new Dialects($mine)->get('roll20'));
        self::assertSame(['roll20', 'foundry', 'avrae', 'diceparser'], new Dialects($mine)->names());

        $this->expectExceptionObject(InvalidDefinition::because('No dialect is named "fantasy"; the dialects are roll20, foundry, avrae, diceparser.'));
        $dialects->get('fantasy');
    }
}
