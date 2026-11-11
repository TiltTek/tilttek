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
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Reader\RolisteamReader;
use Tilttek\Dice\Reader\RolisteamRewriter;

#[CoversClass(RolisteamReader::class)]
#[CoversClass(RolisteamRewriter::class)]
#[CoversClass(DiceRoller::class)]
#[CoversClass(InvalidExpression::class)]
final class RolisteamReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function commands(): iterable
    {
        yield 'dice' => ['3D6+2', '3d6+2'];
        yield 'keep' => ['4d6k3', '4d6kh3'];
        yield 'keep the lowest' => ['4d6kl1', '4d6kl1'];
        yield 'keep and explode' => ['5D10K3', '5d10!kh3'];
        yield 'keep and explode spaced' => ['5D10K 3', '5d10!kh3'];
        yield 'K alone' => ['$1K', '$1K'];
        yield 'reroll once' => ['4d6r[1]', '4d6ro1'];
        yield 'reroll once on a face' => ['4d6r1', '4d6ro1'];
        yield 'reroll until' => ['4d6R[<3]', '4d6r<3'];
        yield 'explosion' => ['3D10e10', '3d10!10'];
        yield 'explosion on a validator' => ['3d10e[>8]', '3d10!>8'];
        yield 'explosion on the highest face' => ['3d10e', '3d10!'];
        yield 'explosion limited' => ['3d10e(3)[10]', '3d10!10(3)'];
        yield 'explosion limited by a roll' => ['3d10e(1d4)[>=8]', '3d10!>=8(1d4)'];
        yield 'explosion limited on a face' => ['3d10e(2)9', '3d10!9(2)'];
        yield 'explosion limited on a comparison' => ['3d10e(2)>8', '3d10!>8(2)'];
        yield 'explosion limited on a negative value' => ['3d[-2..2]e(2)>=-1', '3d[-2..2]!>=-1(2)'];
        yield 'explosion limited on its highest face' => ['3d10e(2)+1', '3d10!(2)+1'];
        yield 'explosion limited, spaced' => ['3d10 e ( 2 ) [ 10 ] + 1', '3d10!10(2)+1'];
        yield 'explosion limited by nested parentheses' => ['3d10e((1+1))[10]', '3d10!10(1+1)'];
        yield 'reroll and add' => ['3d10a[10]', '3d10!!10(1)'];
        yield 'sort descending' => ['10d6s', '10d6sd'];
        yield 'sort ascending' => ['10d6sl', '10d6sa'];
        yield 'count' => ['6d10c[>=7]', '6d10>=7'];
        yield 'count on a face' => ['6d10c10', '6d10=10'];
        yield 'count on a comparison' => ['6d10c>7', '6d10>7'];
        yield 'count of a difference' => ['6d10c!=1', '6d10c[!=1]'];
        yield 'count of a negative face' => ['4d[-1..1]c-1', '4d[-1..1]=-1'];
        yield 'count of all' => ['6d10c[*>2]', '6d10c[all:>2]'];
        yield 'count of any' => ['6d10c[.10]', '6d10c[any:10]'];
        yield 'count of the total' => ['6d10c[:>=30]', '6d10c[total:>=30]'];
        yield 'count of each value' => ['6d10c[?>=7]', '6d10>=7'];
        yield 'the dice before' => ['8d10c[>=6]-@c[=1]', '8d10>=6-@=1'];
        yield 'filter' => ['4d6f[!=6]', '4d6f[!=6]'];
        yield 'filter of all' => ['4d6f[*>1]', '4d6f[all:>1]'];
        yield 'unique' => ['4d6u', '4d6u'];
        yield 'all the same' => ['2d6t', '2d6t'];
        yield 'transformation' => ['5d10T[>5]{15}', '5d10t[>5:15]'];
        yield 'transformations and a default' => ['5d10T[>5]{15}[<2]{-1}{0}', '5d10t[>5:15,<2:-1,<1|>0:0]'];
        yield 'transformation spaced' => ['5d10T [ >5 ] { 15 } + 1', '5d10t[>5:15]+1'];
        yield 'transformation of each value' => ['5d10T[?>5]{15}', '5d10t[>5:15]'];
        yield 'T alone' => ['5d10T+1', '5d10t+1'];
        yield 'paint' => ['8d10p[2:blue]', '8d10look[2:blue]'];
        yield 'p alone' => ['2d6p', '2d6p'];
        yield 'occurrences' => ['10d10o', '10d10o2'];
        yield 'occurrences of a width' => ['10d10o3', '10d10o3'];
        yield 'occurrences of a width and a height' => ['10d10o2,7', '10d10o2[>=7]'];
        yield 'occurrences of a width, then an instruction' => ['10d10o2;1d6', '10d10o2, 1d6'];
        yield 'groups' => ['5d10g10', '5d10g10'];
        yield 'a list of values' => ['[10,25,43,8]k1', '[10,25,43,8]kh1'];
        yield 'dice on a range' => ['4d[-1..1]', '4d[-1..1]'];
        yield 'instructions' => ['8d10;$1+1;@1c[>=6]', '8d10, $1+1, @1>=6'];
        yield 'an item' => ['8d10;$1[0]', '8d10, $1[0]'];
        yield 'an if' => ['1d20i[>=15]{"hit"}{"miss"}', '1d20i[>=15]{"hit"}{"miss"}'];
        yield 'an if of any die' => ['3d6i[.6]{"six"}', '3d6i[any:6]{"six"}'];
        yield 'a switch' => ['1d6S[1..2]{"a"}[3..5]{"b"}{"c"}', '1d6S[1..2]{"a"}[3..5]{"b"}{"c"}'];
        yield 'an exclusive switch of the total' => ['3d6S^[:>10]{"high"}[*<3]{"low"}', '3d6S^[total:>10]{"high"}[all:<3]{"low"}'];
        yield 'operators in a branch' => ['1d20i[>15]{2d6r1s}', '1d20i[>15]{2d6ro1sd}'];
        yield 'a text list' => ['1L[sword,bow,arrow]', '1L[sword,bow,arrow]'];
        yield 'a text list of operator letters' => ['2L[rest,ekes,sly,Kay,T,pyre,om,cot]', '2L[rest,ekes,sly,Kay,T,pyre,om,cot]'];
        yield 'a text list in brackets' => ['1L[a[2],b]', '1L[a[2],b]'];
        yield 'a comment between brackets' => ['2d6[rest]+1', '2d6+1'];
        yield 'a comment' => ['2d6 # rest of the roll', '2d6'];
        yield 'a comment, then an instruction' => ["2d6 # rest\n;1d8e", '2d6, 1d8!'];
        yield 'functions' => ['floor(1d6/3)+ceil(7/2)+round(1d6/2)+abs(1-1d6)+min(1d6,3)+max(1,2)', 'floor(1d6/3)+ceil(7/2)+round(1d6/2)+abs(1-1d6)+min(1d6, 3)+max(1, 2)'];
        yield 'functions in capitals' => ['FLOOR(7/2)', 'floor(7/2)'];
        yield 'repeat' => ['repeat(4d6k3,6)', 'repeat(4d6kh3, 6)'];
        yield 'a word that is no function' => ['ro(1)', 'ro(1)'];
        yield 'a function name without parenthesis' => ['3d6 abs', '3d6 abs'];
        yield 'power and remainder' => ['2^3+10%3', '2^3+10%3'];
        yield 'a sentence' => ['1d20;"$1 rolled, %1 once"', '1d20, "$1 rolled, %1 once"'];
        yield 'merged instructions' => ['1d6;1d8;m[1,2]k1', '1d6, 1d8, m[1,2]kh1'];
        yield 'bound instructions' => ['1d6;1d8;b[1,2]k1', '1d6, 1d8, b[1,2]kh1'];
        yield 'an e and an r after an operand' => ['$1e', '$1e'];
    }

    #[DataProvider('commands')]
    public function testACommandIsReadIntoTheTree(string $command, string $canonical): void
    {
        try {
            $written = new NotationWriter()->write(new RolisteamReader()->read($command));
        } catch (InvalidExpression $invalidExpression) {
            $written = $invalidExpression->input;
        }

        self::assertSame($canonical, $written);
    }

    public function testTheRollerReadsACommand(): void
    {
        $dice = new DiceRoller();

        self::assertSame('5d10!kh3', $dice->write($dice->readRolisteam('5D10K3')));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function errors(): iterable
    {
        yield 'unexpected after a copy' => ['2d6x', 3, 'Unexpected "x"'];
        yield 'unexpected within a copied token' => ['2d6+12345678901', 4, 'A number is at most 1000000000'];
        yield 'unexpected after a replaced token' => ['2d6r[x]', 5, 'Unexpected "x"'];
        yield 'unexpected after spaces' => ['2d6 +  x', 7, 'Unexpected "x"'];
        yield 'unexpected end' => ['2d6+', 4, 'Unexpected end'];
        yield 'unexpected end after spaces' => ['2d6+   ', 7, 'Unexpected end'];
        yield 'a replaced token given twice' => ['2d6r1r2', 5, 'A modifier is given twice'];
        yield 'the split of explosions' => ['4d6e6y', 5, 'The split of explosions "y" is not read'];
        yield 'a merge of every instruction' => ['1d6;1d8;m', 8, '"m" is read with the instructions it takes: m[1,2]'];
        yield 'a bind of every instruction' => ['1d6;1d8;bk1', 8, '"b" is read with the instructions it takes: b[1,2]'];
        yield 'a count without a threshold' => ['4d6c>', 5, 'Unexpected end'];
        yield 'a count with a sign alone' => ['4d6c!x', 5, 'Unexpected "x"'];
        yield 'a transformation without its value' => ['5d10T[>5]15', 9, 'Unexpected "15"'];
        yield 'a transformation not closed' => ['5d10T[>5', 8, 'Unexpected end'];
        yield 'a transformation with its value not closed' => ['5d10T[>5]{15', 12, 'Unexpected end'];
        yield 'a transformation without value' => ['5d10T[>5]{}', 10, 'Unexpected "}"'];
        yield 'an explosion limit not closed' => ['3d10e(3', 7, 'Unexpected end'];
        yield 'a scope written twice' => ['6d10c[**2]', 7, 'Unexpected "*"'];
        yield 'a sort spaced from its l' => ['10d6s l', 6, 'Unexpected "l"'];
        yield 'an unclosed quote' => ['1d6;"a', 4, 'Unclosed quote'];
        yield 'a negative explosion limit' => ['3d10e(0)[10]', 5, 'An explosion is limited to at least one per die; 0 is not'];
    }

    #[DataProvider('errors')]
    public function testAnErrorIsPlacedInTheCommandAsWritten(string $command, int $position, string $reason): void
    {
        try {
            new RolisteamReader()->read($command);
            self::fail('The command reads.');
        } catch (InvalidExpression $invalidExpression) {
            self::assertSame([$command, $position, $reason, \sprintf('%s at position %d of "%s".', $reason, $position, $command)], [$invalidExpression->input, $invalidExpression->position, $invalidExpression->reason, $invalidExpression->getMessage()]);
        }
    }

    public function testACommandNamesMacrosWhoseNamesAreNotRewritten(): void
    {
        $macros = new class implements Macros {
            #[Override]
            public function pool(string $name): ?Pool
            {
                return 'fire2' === $name ? new DiceParser()->parse('8d6') : null;
            }
        };

        self::assertSame('8d6+1, @1sd', new NotationWriter()->write(new RolisteamReader()->read('$Fire2+1;@1s', $macros)));
    }

    public function testACommandIsWithinTheLength(): void
    {
        $this->expectException(LimitExceeded::class);

        new RolisteamReader(new Limits(length: 5))->read('3d6+10');
    }

    public function testACommandWrittenLongerKeepsTheLimitsOfTheRoll(): void
    {
        $limits = new Limits(dice: 10, length: 9);

        self::assertSame('4d6sd, 4d6sd', new NotationWriter()->write(new RolisteamReader($limits)->read('4d6s;4d6s')));

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 10, 12));
        new RolisteamReader($limits)->read('6d6s;6d6s');
    }
}
