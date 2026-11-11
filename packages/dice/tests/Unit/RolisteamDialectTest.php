<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Dialect\Dialect;
use Tilttek\Dice\Dialect\RolisteamDiceParser;
use Tilttek\Dice\Dialect\Translation;
use Tilttek\Dice\Dialect\Unsupported;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Notation\NotationWriter;

#[CoversClass(RolisteamDiceParser::class)]
#[CoversClass(Dialect::class)]
#[CoversClass(Translation::class)]
#[CoversClass(NotationWriter::class)]
final class RolisteamDialectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function expressions(): iterable
    {
        yield 'plain' => ['2d6+3', '2d6+3'];
        yield 'keep' => ['4d6kh3+4d6kl1', '4d6k3+4d6kl1'];
        yield 'drop the lowest' => ['4d6dl1', '4d6k3'];
        yield 'drop the highest' => ['4d6dh1', '4d6kl3'];
        yield 'drop after a reroll' => ['4d6r1dl1', '4d6R[1]k3'];
        yield 'drop after an explosion' => ['4d6!dl1', '✗ 4d6!dl1'];
        yield 'drop after a compound explosion' => ['4d6!!6(1)dl1', '4d6a[6]k3'];
        yield 'drop after dice all the same' => ['4d6tdl1', '✗ 4d6tdl1'];
        yield 'drop after a filter' => ['4d6f[>1]dl1', '✗ 4d6f[>1]dl1'];
        yield 'drop from a list of values' => ['[10,25,43]dl1', '[10,25,43]k2'];
        yield 'drop from a group' => ['{1d6, 1d8, 1d10}dh1', '{1d6,1d8,1d10}kl2'];
        yield 'drop from the dice before' => ['4d6+@dl1', '✗ @dl1'];
        yield 'reroll once' => ['4d6ro<3', '4d6r[<3]'];
        yield 'reroll repeated' => ['4d6r1', '4d6R[1]'];
        yield 'reroll on a validator' => ['4d6r[1|2]', '4d6R[1|2]'];
        yield 'unique' => ['4d6u', '4d6u'];
        yield 'unique once' => ['4d6uo', '✗ 4d6uo'];
        yield 'explosion' => ['3d10!', '3d10e[10]'];
        yield 'explosion on a validator' => ['3d10!>8', '3d10e[>8]'];
        yield 'explosion limited' => ['3d10!(3)', '3d10e(3)[10]'];
        yield 'explosion limited by a roll' => ['3d10!>=8(1d4)', '3d10e(1d4)[>=8]'];
        yield 'compound explosion once' => ['3d10!!10(1)', '3d10a[10]'];
        yield 'compound explosion on the highest face' => ['3d10!!(1)', '3d10a[10]'];
        yield 'compound explosion' => ['3d10!!', '✗ 3d10!!'];
        yield 'compound explosion twice' => ['3d10!!(2)', '✗ 3d10!!(2)'];
        yield 'penetrating explosion' => ['3d10!p', '✗ 3d10!p'];
        yield 'explosion of rolled sides' => ['3d(1d6+4)!', '✗ 3d(1d6+4)!'];
        yield 'explosion of rolled sides on a face' => ['3d(1d6+4)!6', '3d(1d6+4)e[6]'];
        yield 'dice all the same' => ['2d6t', '2d6t'];
        yield 'floor and ceiling' => ['4d6min2max5', '✗ 4d6min2max5, 4d6min2max5'];
        yield 'transformations' => ['5d10t[>5:15,<=2:-1]', '5d10T[>5]{15}[<=2]{-1}'];
        yield 'transformation of each die' => ['5d10t[die:>5:15]', '✗ 5d10t[die:>5:15]'];
        yield 'filter' => ['4d6f[!=6]', '4d6f[!=6]'];
        yield 'filter of all' => ['4d6f[all:>1]', '4d6f[*>1]'];
        yield 'successes' => ['6d10>=7', '6d10c[>=7]'];
        yield 'successes of any' => ['6d10c[any:10]', '6d10c[.10]'];
        yield 'successes of the total' => ['6d10c[total:>=30]', '6d10c[:>=30]'];
        yield 'failures' => ['6d10>=7f1', '✗ 6d10>=7f1'];
        yield 'occurrences' => ['10d10o', '10d10o2'];
        yield 'occurrences from a height' => ['10d10o3[>=7]', '10d10o3,7'];
        yield 'occurrences above a height' => ['10d10o3[>6]', '10d10o3,7'];
        yield 'occurrences of a height' => ['10d10o3[7]', '✗ 10d10o3[7]'];
        yield 'occurrences in a range' => ['10d10o3[5..7]', '✗ 10d10o3[5..7]'];
        yield 'groups' => ['5d10g10', '5d10g10'];
        yield 'critical marks' => ['1d20cs', '✗ 1d20cs'];
        yield 'critical failures' => ['1d20cf', '✗ 1d20cf'];
        yield 'look tokens' => ['8d10look[2:accent,muted]', '8d10p[2:accent,muted]'];
        yield 'sorts' => ['4d6sd+4d6sa', '4d6s+4d6sl'];
        yield 'fudge dice' => ['4dF', '4d[-1..1]'];
        yield 'weighted fudge dice' => ['4dF.1', '✗ 4dF.1'];
        yield 'dice on a range' => ['4d[0..9]', '4d[0..9]'];
        yield 'custom dice' => ['3d{chromatic}', '✗ 3d{chromatic}'];
        yield 'a rolled count' => ['(1d4+1)d6', '(1d4+1)d6'];
        yield 'a rolled count of dice on a range' => ['(1d4)d[0..9]', '(1d4)d[0..9]'];
        yield 'a rolled count of fudge dice' => ['(1d4)dF', '(1d4)d[-1..1]'];
        yield 'a rolled count of weighted fudge dice' => ['(1d4)dF.1', '✗ (1d4)dF.1'];
        yield 'a rolled count of custom dice' => ['(1d4)d{chromatic}', '✗ (1d4)d{chromatic}'];
        yield 'a list of values' => ['[10,-25,43]kh1', '[10,-25,43]k1'];
        yield 'the dice before' => ['8d10>=6-@=1', '8d10c[>=6]-@c[1]'];
        yield 'instructions' => ['8d10, $1+1, $1[0], @1>=6', '8d10;$1+1;$1[0];@1c[>=6]'];
        yield 'bound dice' => ['1d6, 1d8, b[1,2]kh1', '✗ b[1,2]kh1'];
        yield 'merged dice' => ['1d6, m[1]', '✗ m[1]'];
        yield 'a group' => ['{4d6, 2d10}kh1', '{4d6,2d10}k1'];
        yield 'a text list' => ['2Lu[a[3],"b, c"]', '2Lu[a[3],"b, c"]'];
        yield 'a joined text list' => ['(1d3)Ln[ka,ro]', '(1d3)Ln[ka,ro]'];
        yield 'a sentence' => ['1d20, "$1 rolled, \\%1 off"', '1d20;"$1 rolled, \\%1 off"'];
        yield 'an if' => ['1d20i[>=15]{"hit"}{1d6ro1}', '1d20i[>=15]{"hit"}{1d6r[1]}'];
        yield 'an if without else' => ['(1d20+5)i[all:>=15]{"hit"}', '(1d20+5)i[*>=15]{"hit"}'];
        yield 'a switch' => ['1d6S[1..2]{"a"}[3..5]{"b"}{"c"}', '1d6S[1..2]{"a"}[3..5]{"b"}{"c"}'];
        yield 'an exclusive switch without default' => ['1d6S^[1..2]{"a"}', '1d6S^[1..2]{"a"}'];
        yield 'rounding functions' => ['floor(1d6/3)+ceil(7/2)+round(1d6/2)', 'floor(1d6/3)+ceil(7/2)+round(1d6/2)'];
        yield 'a division' => ['10/3', 'floor(10/3)'];
        yield 'rounding of a negative half' => ['round((1d6-4)/2)', '✗ (1d6-4)/2'];
        yield 'rounding of a negative divisor' => ['round(7/(1-1d6))', '✗ 7/(1-1d6)'];
        yield 'functions' => ['abs(1-1d6)+min(1d6, 3)+max(1, 2)', 'abs(1-1d6)+min(1d6, 3)+max(1, 2)'];
        yield 'repeat' => ['repeat(4d6kh3, 6)', 'repeat(4d6k3, 6)'];
        yield 'remainder' => ['1d20%3', '1d20%3'];
        yield 'remainder of a negative value' => ['(1d6-3)%2', '✗ (1d6-3)%2'];
        yield 'remainder by a negative value' => ['7%(1-1d6)', '✗ 7%(1-1d6)'];
        yield 'power' => ['2^3*2', '(2^3)*2'];
        yield 'power of a negative exponent' => ['2^-1', '✗ 2^-1'];
    }

    #[DataProvider('expressions')]
    public function testAPoolIsWrittenInTheNotationOfDiceParser(string $input, string $expected): void
    {
        $dice = new DiceRoller();
        $translation = $dice->translate($dice->parse($input), 'diceparser');

        self::assertSame($expected, $translation->isComplete() ? $translation->expressions[0] : '✗ '.implode(', ', array_map(static fn (Unsupported $unsupported): string => $unsupported->construct, $translation->unsupported)));

        if ($translation->isComplete()) {
            self::assertSame($translation->expressions, $dice->translate($dice->readRolisteam($translation->expressions[0]), 'diceparser')->expressions);
        }
    }

    public function testEveryInstructionIsOneCommand(): void
    {
        $dice = new DiceRoller();
        $translation = $dice->translate($dice->parse('2d6; 1d8/2'), 'diceparser', RoundingMode::PositiveInfinity);

        self::assertSame(['diceparser', '!', ['2d6;floor(1d8/2)'], ['!2d6;floor(1d8/2)'], ['1d8/2 is written rounded down; the roll rounds it PositiveInfinity.']], [$translation->dialect, $translation->command, $translation->expressions, $translation->commands(), $translation->notices]);
    }

    public function testTheReasonsAreGiven(): void
    {
        $dice = new DiceRoller();
        $reasons = static fn (string $input): array => array_map(static fn (Unsupported $unsupported): string => $unsupported->reason, $dice->translate($dice->parse($input), 'diceparser')->unsupported);

        self::assertSame(['a unique reroll once is not written', 'a floor is not written', 'a ceiling is not written', 'a count of failures is not written', 'a critical mark is not written'], $reasons('6d10uomin2max9>=7f1cs'));
        self::assertSame(['a critical mark is not written', 'a penetrating explosion is not written'], $reasons('3d10!pcf'));
        self::assertSame(['a compound explosion is written once at most'], $reasons('3d10!!'));
        self::assertSame(['an explosion names the face it explodes on when the sides are rolled first'], $reasons('3d(1d6)!'));
        self::assertSame(['dropped dice are written as the dice kept, for a known number of dice'], $reasons('4d6!dl1'));
        self::assertSame(['occurrences are judged by their lowest value'], $reasons('10d10o3[<7]'));
        self::assertSame(['a condition on each die as it fell is not written'], $reasons('5d10c[die:>5]'));
        self::assertSame(['the dice of several instructions are not bound or merged'], $reasons('1d6, 1d8, b[1,2]'));
        self::assertSame(['round() may round the negative halves otherwise'], $reasons('round((1d6-4)/2)'));
        self::assertSame(['a remainder of a negative value may have the sign of the dividend'], $reasons('(1d6-3)%2'));
        self::assertSame(['a power whose exponent may be negative is not written'], $reasons('2^-1'));
        self::assertSame(['weighted Fudge dice are not written'], $reasons('(1d4)dF.1'));
        self::assertSame(['custom dice are declared by each site'], $reasons('(1d4)d{chromatic}'));
    }

    public function testARolledCountWrittenAsANumberIsOneThatCannotBeNegative(): void
    {
        $dialect = new RolisteamDiceParser();
        $written = static fn (ComputedDice $dice): string => $dialect->translate(new Pool($dice))->expressions[0];

        self::assertSame(['2d[0..9]', '0d[-1..1]', '(-1)d6'], [
            $written(new ComputedDice(new Constant(2), new RangeDie(0, 9))),
            $written(new ComputedDice(new Constant(0), new FudgeDie())),
            $written(new ComputedDice(new Constant(-1), new StandardDie(6))),
        ]);
    }
}
