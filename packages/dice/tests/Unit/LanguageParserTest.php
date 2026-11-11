<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Branching;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\ListItem;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Placeholder;
use Tilttek\Dice\Expression\PlaceholderKind;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\Range;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\SelectionMode;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\ExpressionWriter;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Parser\Lexer;
use Tilttek\Dice\Parser\Token;

#[CoversClass(DiceParser::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(NotationWriter::class)]
#[CoversClass(ExpressionWriter::class)]
#[CoversClass(Pool::class)]
#[CoversClass(Reference::class)]
#[CoversClass(InstructionDice::class)]
#[CoversClass(Group::class)]
#[CoversClass(ListItem::class)]
#[CoversClass(TextList::class)]
#[CoversClass(Placeholder::class)]
#[CoversClass(PlaceholderKind::class)]
#[CoversClass(Sentence::class)]
#[CoversClass(Conditional::class)]
#[CoversClass(SwitchCase::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Branching::class)]
#[CoversClass(Limits::class)]
#[CoversClass(FunctionCall::class)]
#[CoversClass(MathFunction::class)]
#[CoversClass(InvalidExpression::class)]
#[CoversClass(Range::class)]
#[CoversClass(Token::class)]
final class LanguageParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalForms(): iterable
    {
        yield 'instructions' => ['2d6; 1d8 ;3', '2d6, 1d8, 3'];
        yield 'instructions and expressions together' => ['2d6, 1d8; 3', '2d6, 1d8, 3'];
        yield 'a reference' => ['1d6;$1', '1d6, $1'];
        yield 'a reference to an item' => ['1d6; $1[0] + $1', '1d6, $1[0]+$1'];
        yield 'a reference then a comment' => ['1d6; $1[x]', '1d6, $1'];
        yield 'a reference then a comment of digits' => ['1d6; $1[1 2]', '1d6, $1'];
        yield 'the dice of an instruction' => ['1d6 ; @1kh1', '1d6, @1kh1'];
        yield 'the dice of an instruction, counted' => ['8d10; @1c[>=6]', '8d10, @1>=6'];
        yield 'the dice before, after the dice of an instruction' => ['1d6; @1kh1+@', '1d6, @1kh1+@'];
        yield 'dice bound' => ['1d6;1d8;b[1, 2]kh1', '1d6, 1d8, b[1,2]kh1'];
        yield 'the dice of one instruction bound' => ['1d6;1d8;B[1]', '1d6, 1d8, @1'];
        yield 'dice merged' => ['1d6;1d8;m[1,2]sd', '1d6, 1d8, m[1,2]sd'];
        yield 'the dice of one instruction merged' => ['1d6;1d8;M[2]', '1d6, 1d8, m[2]'];
        yield 'a group' => ['{ 4d6 , 2d10 }kh1', '{4d6, 2d10}kh1'];
        yield 'a group of one' => ['{3d6}>=10', '{3d6}>=10'];
        yield 'a group sorted' => ['{1d6,1d8}sd', '{1d6, 1d8}sd'];
        yield 'a group, then the dice before' => ['{1d6,1d8}+@kh1', '{1d6, 1d8}+@kh1'];
        yield 'a text list' => ['l[sword, bow]', '1L[sword,bow]'];
        yield 'a text list with a count' => ['3L[a,b]', '3L[a,b]'];
        yield 'a text list of unique items' => ['3LU[a,b,c]', '3Lu[a,b,c]'];
        yield 'a text list joined' => ['3Ln[ka,ro]', '3Ln[ka,ro]'];
        yield 'a text list with both letters' => ['2Lnu[a,b]', '2Lun[a,b]'];
        yield 'a text list counted by a roll' => ['(1d4)L[a,b]', '(1d4)L[a,b]'];
        yield 'a text list with weights' => ['2L[a[3], b, c[1]]', '2L[a[3],b,c]'];
        yield 'a text list with weights of several digits' => ['L[a[12]]', '1L[a[12]]'];
        yield 'a text list whose items end with spaces' => ['L[a , b c ]', '1L[a,b c]'];
        yield 'a text list item with a number inside' => ['1L[x2..3:y]', '1L["x2..3:y"]'];
        yield 'a text list item with signs of placeholders' => ['1L["a,%1 $2 @3"]', '1L["a,%1 $2 @3"]'];
        yield 'a text list item beyond ASCII between quotes' => ['1L["é, è"]', '1L["é, è"]'];
        yield 'a text list on ranges' => ['1L[-1..0:x, 1..2 : y]', '1L[-1..0:x,1..2:y]'];
        yield 'a text list on ranges, quoted' => ['1L[1..1:"a", 2..2:b[1]]', '1L[1..1:a,2..2:b]'];
        yield 'a text list of texts beyond ASCII' => ['1L[épée, arc long]', '1L[épée,arc long]'];
        yield 'a text list of numbers' => ['1L[1, 10]', '1L[1,10]'];
        yield 'a text list of signs' => ['1L[$1, @, %, a:b, (x)]', '1L[$1,@,%,a:b,(x)]'];
        yield 'a text list item of a number too large' => ['1L[99999999999 coins]', '1L[99999999999 coins]'];
        yield 'a text list item that looks like a range' => ['1L[1..2 x]', '1L["1..2 x"]'];
        yield 'a text list item of a quote inside' => ['1L[a"b"]', '1L["a\"b\""]'];
        yield 'text list items that need quotes' => ['1L["a,b", "c[d]", "x..y", "q\"r", "back\\\\slash", "a//b", "#", " a ", "{", "}", "]", "line\nbreak"]', '1L["a,b","c[d]","x..y","q\"r","back\\\\slash","a//b","#"," a ","{","}","]","line\nbreak"]'];
        yield 'a text list item of a translation key' => ['1L[weapon.sword, weapon.bow]', '1L[weapon.sword,weapon.bow]'];
        yield 'a text' => ['"Critical!"', '"Critical!"'];
        yield 'a text with a line break' => ["\"a\\nb\nc\"", '"a\nb\nc"'];
        yield 'a text with escaped signs' => ['"50\% \$1 \@2 \"q\" \\\\"', '"50% \$1 \@2 \"q\" \\\\"'];
        yield 'a text with signs alone' => ['"100 % $ @ $x"', '"100 % $ @ $x"'];
        yield 'a sentence' => ['1d6; 1d8; "%1 and %2, %3: $1, $2[0], @2"', '1d6, 1d8, "%1 and %2, %3: $1, $2[0], @2"'];
        yield 'a sentence whose placeholders are followed by digits' => ['1d6; "$1\2 $1\[0] %1\0 @1\9"', '1d6, "$1\2 $1\[0] %1\0 @1\9"'];
        yield 'a sentence whose item is not one' => ['1d6; "cost $1[x]"', '1d6, "cost $1\[x]"'];
        yield 'a sentence whose item is not closed' => ['1d6; "cost $1[2"', '1d6, "cost $1\[2"'];
        yield 'a sentence with an item, then a bracket' => ['1d6; "$1[0]["', '1d6, "$1[0]["'];
        yield 'a sentence with leading zeros' => ['1d6; "$01"', '1d6, "$1"'];
        yield 'a sentence with an item of several digits' => ['1d6; "$1[12]"', '1d6, "$1[12]"'];
        yield 'a sentence whose dice take no item' => ['1d6; "@1[0]"', '1d6, "@1[0]"'];
        yield 'a sentence beyond ASCII' => ['1d6; "é $1 è"', '1d6, "é $1 è"'];
        yield 'an if' => ['1d20 i [>=15] {"hit"} {"miss"}', '1d20i[>=15]{"hit"}{"miss"}'];
        yield 'an if without else' => ['(1d6+1)I[>3]{1}', '(1d6+1)i[>3]{1}'];
        yield 'an if on all the dice' => ['3d6i[all:>3]{1}{2}', '3d6i[all:>3]{1}{2}'];
        yield 'an if on any die' => ['3d6i[any:6]{1}', '3d6i[any:6]{1}'];
        yield 'an if on the total' => ['3d6i[total:>10]{1}', '3d6i[total:>10]{1}'];
        yield 'two ifs' => ['1d6i[>3]{1}i[>0]{3}', '1d6i[>3]{1}i[>0]{3}'];
        yield 'an if negated' => ['-1d6i[>3]{1}{2}', '-1d6i[>3]{1}{2}'];
        yield 'an if to a power' => ['1d6i[>3]{1}{2}^2', '1d6i[>3]{1}{2}^2'];
        yield 'a power of an if' => ['2^1d6i[>3]{1}{2}', '2^1d6i[>3]{1}{2}'];
        yield 'an if in an if' => ['1d6i[>3]{1d4i[1]{"one"}{"other"}}{"low"}', '1d6i[>3]{1d4i[1]{"one"}{"other"}}{"low"}'];
        yield 'an if with a sentence' => ['1d6i[>3]{"%1 then %2"}', '1d6i[>3]{"%1 then %2"}'];
        yield 'an if judging the dice before' => ['2d6i[>6]{@kh1}', '2d6i[>6]{@kh1}'];
        yield 'a switch' => ['1d6s[1..2]{"a"}[3]{"b"}{"c"}', '1d6S[1..2]{"a"}[3]{"b"}{"c"}'];
        yield 'an exclusive switch' => ['1d6S^[1]{2}[2]{3}', '1d6S^[1]{2}[2]{3}'];
        yield 'a switch after a sort' => ['4d6sas[1]{"a"}', '4d6saS[1]{"a"}'];
        yield 'a sort to a power' => ['4d6s^2', '4d6sa^2'];
        yield 'a sort to the power of a group' => ['1d6S^{1}', '1d6sa^{1}'];
        yield 'a merged instruction referred to' => ['1d6; m[1]; $1', '1d6, m[1], $1'];
        yield 'a switch on a scope' => ['3d6S[any:6]{"six"}', '3d6S[any:6]{"six"}'];
        yield 'a switch on a sum' => ['(1d6+1)S[7]{"seven"}', '(1d6+1)S[7]{"seven"}'];
        yield 'repeat' => ['REPEAT(4d6kh3, 6)', 'repeat(4d6kh3, 6)'];
        yield 'repeat a text' => ['repeat("x", 2)', 'repeat("x", 2)'];
        yield 'repeat by a roll' => ['repeat(1d6, 1d4)', 'repeat(1d6, 1d4)'];
        yield 'repeat a list' => ['repeat(L[a,b], 2)', 'repeat(1L[a,b], 2)'];
        yield 'a comment at the end' => ['2d6 # fire damage', '2d6'];
        yield 'a comment to the end of the line' => ["2d6 // fire\n; 1d8", '2d6, 1d8'];
        yield 'a comment between brackets' => ['2d6 [fire] + 1d4[cold]', '2d6+1d4'];
        yield 'a comment between brackets after a list' => ['[1,2]kh1[best]', '[1,2]kh1'];
    }

    #[DataProvider('canonicalForms')]
    public function testAnInputParsesToItsCanonicalForm(string $input, string $canonical): void
    {
        $pool = new DiceParser()->parse($input);

        self::assertSame($canonical, $pool->canonical());
        self::assertSame($canonical, new DiceParser()->parse($canonical)->canonical());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'a reference to itself' => ['$1', 1, 'Instruction 1 does not come before this one'];
        yield 'a reference to an instruction after' => ['1d6; $3', 6, 'Instruction 3 does not come before this one'];
        yield 'a reference to none' => ['1d6; $0', 6, 'Instruction 0 does not come before this one'];
        yield 'a reference without a number' => ['1d6; $x', 6, 'Unexpected "x"'];
        yield 'a reference apart from its number' => ['1d6; $ 1', 7, 'Unexpected "1"'];
        yield 'the dice of an instruction after' => ['@2; 1d6', 1, 'Instruction 2 does not come before this one'];
        yield 'the dice of an instruction, rerolled' => ['1d6; @1r1', 5, 'The dice of instructions before are not rolled again: they take no reroll and no explosion'];
        yield 'the dice of an instruction apart from its number' => ['1d6; @ 1', 5, '"@" judges the dice of a set before it, and none comes before'];
        yield 'the dice of a merged instruction' => ['1d6; m[1]; @1', 12, 'Instruction 1 gave its dice to a merge'];
        yield 'a merged instruction merged again' => ['1d6; 1d8; m[1]; m[1,2]', 18, 'Instruction 1 gave its dice to a merge'];
        yield 'a merged instruction bound' => ['1d6; 1d8; m[1]; b[2,1]', 20, 'Instruction 1 gave its dice to a merge'];
        yield 'the second instruction of a merge, taken again' => ['1d6; 1d8; m[1,2]; @2', 19, 'Instruction 2 gave its dice to a merge'];
        yield 'the instruction of an earlier merge, taken again' => ['1d6; 1d8; m[1]; m[2]; @1', 23, 'Instruction 1 gave its dice to a merge'];
        yield 'an instruction bound twice' => ['1d6; b[1,1]', 5, 'An instruction gives its dice once'];
        yield 'dice bound from none' => ['1d6; b[]', 7, 'Unexpected "]"'];
        yield 'dice bound unclosed' => ['1d6; b[1', 8, 'Unexpected end'];
        yield 'dice bound, exploding' => ['1d6; b[1]!', 5, 'The dice of instructions before are not rolled again: they take no reroll and no explosion'];
        yield 'a group unclosed' => ['{1d6', 4, 'Unexpected end'];
        yield 'a group seeing the dice outside it' => ['1d6+{@}', 5, '"@" judges the dice of a set before it, and none comes before'];
        yield 'a group with advantage' => ['{1d6}adv', 6, 'Unexpected "d"'];
        yield 'the dice of an instruction with advantage' => ['1d6; @1adv', 8, 'Unexpected "d"'];
        yield 'a text list apart from its bracket' => ['L [a]', 0, 'Unexpected "L"'];
        yield 'a text list with its letters apart' => ['L u[a]', 0, 'Unexpected "L"'];
        yield 'a group empty' => ['{}', 1, 'Unexpected "}"'];
        yield 'a group that explodes' => ['{1d6}!', 0, 'The totals of a group are not rolled again: a group takes no reroll and no explosion'];
        yield 'a group keeping too many' => ['{1d6}kh2', 0, '1 dice cannot keep 2, nor drop all of them'];
        yield 'a group of a text' => ['{"a"}', 1, 'A text or a list is not a number to compute with'];
        yield 'a group of a list' => ['{1d6, L[a]}', 6, 'A text or a list is not a number to compute with'];
        yield 'a group too deep' => [str_repeat('{', 33).'1'.str_repeat('}', 33), 32, 'Too deeply nested'];
        yield 'a text list without items' => ['L[]', 2, 'Unexpected "]"'];
        yield 'a text list unclosed' => ['L[a', 3, 'Unexpected end'];
        yield 'a text list with an empty item' => ['L[a,,b]', 4, 'Unexpected ","'];
        yield 'a text list with an empty text' => ['L["", a]', 2, 'An item of a list has a text'];
        yield 'a text list with a blank text' => ['L[" "]', 2, 'An item of a list has a text'];
        yield 'a text list drawing none' => ['0L[a]', 0, 'A list draws at least one item; 0 is not'];
        yield 'a text list too short for unique items' => ['3Lu[a,b]', 0, '3 different items cannot be drawn from 2'];
        yield 'a text list unique twice' => ['Luu[a]', 2, 'A modifier is given twice'];
        yield 'a text list joined twice' => ['Lnn[a]', 2, 'A modifier is given twice'];
        yield 'a text list of a weight of none' => ['L[a[0]]', 2, 'An item of a list weighs at least 1; 0 does not'];
        yield 'a text list of a weight unclosed' => ['L[a[2]', 6, 'Unexpected end'];
        yield 'a text list of a weight that is a word' => ['L[a[b]]', 4, 'Unexpected "b"'];
        yield 'a text list of a weight, then text' => ['L[a[2]b]', 6, 'Unexpected "b"'];
        yield 'a text list partly on ranges' => ['L[1..2:a, b]', 0, 'The items of a list all cover a range, or none does'];
        yield 'a text list of ranges with a gap' => ['L[1..2:a, 4..5:b]', 0, 'The ranges of a list follow each other: 4..5 comes after 2'];
        yield 'a text list of ranges overlapping' => ['L[1..2:a, 2..5:b]', 0, 'The ranges of a list follow each other: 2..5 comes after 2'];
        yield 'a text list of a range upside down' => ['L[2..1:a]', 2, 'A range goes from its lower bound to its higher one; 2..1 does not'];
        yield 'a text list of a range and a weight' => ['L[1..2:a[2]]', 2, 'An item covers a range or weighs, not both'];
        yield 'a text list counted by a text' => ['("a")L[a]', 0, 'A text or a list is not a number to compute with'];
        yield 'a text list in arithmetic' => ['L[a]+1', 4, 'A text or a list is not a number to compute with'];
        yield 'a text in arithmetic' => ['1-"a"', 1, 'A text or a list is not a number to compute with'];
        yield 'a text multiplied' => ['"a"*2', 3, 'A text or a list is not a number to compute with'];
        yield 'a text negated' => ['-"a"', 0, 'A text or a list is not a number to compute with'];
        yield 'a text to a power' => ['"a"^2', 3, 'A text or a list is not a number to compute with'];
        yield 'a power of a text' => ['2^"a"', 1, 'A text or a list is not a number to compute with'];
        yield 'a repetition in arithmetic' => ['repeat(1,2)+1', 11, 'A text or a list is not a number to compute with'];
        yield 'a text as an argument' => ['abs("a")', 4, 'A text or a list is not a number to compute with'];
        yield 'a text as a count of repetitions' => ['repeat(1, "a")', 10, 'A text or a list is not a number to compute with'];
        yield 'a text as a count of dice' => ['("a")d6', 0, 'A text or a list is not a number to compute with'];
        yield 'a text as sides of dice' => ['1d("a")', 2, 'A text or a list is not a number to compute with'];
        yield 'a text as a limit of explosions' => ['1d6!("a")', 4, 'A text or a list is not a number to compute with'];
        yield 'an if on a text' => ['"a"i[1]{1}', 3, 'A text or a list is not a number to compute with'];
        yield 'a switch on a list' => ['L[a]S[1]{1}', 4, 'A text or a list is not a number to compute with'];
        yield 'repeat with one argument' => ['repeat(1)', 0, 'repeat() takes two arguments; 1 given'];
        yield 'repeat with three arguments' => ['repeat(1,2,3)', 0, 'repeat() takes two arguments; 3 given'];
        yield 'an if on each die' => ['1d6i[die:6]{1}', 3, 'A branch judges its subject as a whole: its value, all or any of its dice, or their total; the scope "die" judges each die'];
        yield 'a switch on each die' => ['1d6S[die:6]{1}', 3, 'A branch judges its subject as a whole: its value, all or any of its dice, or their total; the scope "die" judges each die'];
        yield 'an if without a branch' => ['1d6i[6]', 7, 'Unexpected end'];
        yield 'an if unclosed' => ['1d6i[6]{1', 9, 'Unexpected end'];
        yield 'an if of an empty branch' => ['1d6i[6]{}', 8, 'Unexpected "}"'];
        yield 'a switch without a branch' => ['1d6S[6]', 7, 'Unexpected end'];
        yield 'a branch too deep' => [str_repeat('1i[1]{', 33).'1'.str_repeat('}', 33), 197, 'Too deeply nested'];
        yield 'a text unclosed' => ['"abc', 0, 'Unclosed quote'];
        yield 'a text unclosed after an escape' => ['"abc\"', 0, 'Unclosed quote'];
        yield 'an unknown escape' => ['"a\qb"', 2, 'Unknown escape "\q"'];
        yield 'an unknown escape beyond ASCII' => ['"é\q"', 2, 'Unknown escape "\q"'];
        yield 'an unknown escape in an item' => ['L["é\q"]', 4, 'Unknown escape "\q"'];
        yield 'an unknown escape in an item, further' => ['L[a, "bc\q"]', 8, 'Unknown escape "\q"'];
        yield 'an escape at the end' => ['"a\\\\\\"', 0, 'Unclosed quote'];
        yield 'an unknown placeholder' => ['1d6; "%4"', 6, 'Unknown placeholder "%4"'];
        yield 'a placeholder of two digits' => ['1d6; "%12"', 6, 'Unknown placeholder "%12"'];
        yield 'a subject with nothing before' => ['"%1"', 1, '"%1" speaks of the subject of a branch or the instruction before, and none comes before'];
        yield 'the dice of a subject with nothing before' => ['"%2"', 1, '"%2" speaks of the subject of a branch or the instruction before, and none comes before'];
        yield 'the instructions before, with none' => ['1d6i[1]{"%3"}', 9, '"%3" speaks of the instructions before, and none comes before'];
        yield 'a placeholder of an instruction after' => ['1d6; "$2"', 6, 'Instruction 2 does not come before this one'];
        yield 'a placeholder of no instruction' => ['1d6; "@0"', 6, 'Instruction 0 does not come before this one'];
        yield 'a placeholder too large' => ['1d6; "$99999999999"', 6, 'Instruction 99999999999 does not come before this one'];
        yield 'a comment between brackets unclosed' => ['2d6 [fire', 9, 'Unexpected end'];
        yield 'an instruction left empty' => ['2d6;', 4, 'Unexpected end'];
        yield 'a comment instead of an instruction' => ['# nothing', 9, 'Unexpected end'];
    }

    #[DataProvider('invalidInputs')]
    public function testAnInvalidInputIsRefusedWithItsPosition(string $input, int $position, string $reason): void
    {
        try {
            new DiceParser()->parse($input);
            self::fail('The input parsed.');
        } catch (InvalidExpression $invalidExpression) {
            self::assertSame([\sprintf('%s at position %d of "%s".', $reason, $position, $input), $position], [$invalidExpression->getMessage(), $invalidExpression->position]);
        }
    }

    public function testCommentsStayWithTheirInstruction(): void
    {
        $pool = new DiceParser()->parse("2d6 # fire damage\n; 1d8 [cold] + 2 // note\n; 1d4 #   \n; 3 []");

        self::assertSame([0 => ['fire damage'], 1 => ['cold', 'note']], $pool->comments);
        self::assertSame([], new DiceParser()->parse('2d6')->comments);
        self::assertSame([1 => ['b'], 2 => ['c']], new DiceParser()->parse('1; 2 # b'."\n".'; 3 [c]')->comments);
        self::assertSame([0 => ['a', 'b']], new DiceParser()->parse('1 # b'."\n".'[a]')->comments);
        self::assertSame([1 => ['feu']], new DiceParser()->parse('"é"; 2d6 [ feu ]')->comments);
        self::assertSame([], new Pool(new Constant(1))->comments);
        self::assertSame([0 => ['x']], new Pool(new Constant(1))->withComments([0 => ['x']])->comments);
    }

    public function testAParserRecoversItsStateBetweenInputs(): void
    {
        $parser = new DiceParser();

        self::assertSame('1d6, 1d8, m[1]', $parser->parse('1d6; 1d8 # a'."\n".'; m[1]')->canonical());
        self::assertSame('1d6, @1', $parser->parse('1d6; @1')->canonical());
        self::assertSame([], $parser->parse('2')->comments);

        try {
            $parser->parse('1d6i[1]{"%1');
        } catch (InvalidExpression) {
        }

        $this->expectException(InvalidExpression::class);
        $parser->parse('"%1"');
    }

    public function testGroupsAndBranchesLeaveTheirDepthBehind(): void
    {
        $long = new DiceParser(new Limits(length: 1000));

        self::assertSame(str_repeat('{1}+', 40).'1', $long->parse(str_repeat('{1}+', 40).'1')->canonical());
        self::assertSame(str_repeat('1i[1]{1}+', 40).'1', $long->parse(str_repeat('1i[1]{1}+', 40).'1')->canonical());
    }

    public function testTheDefaultLimitsOfTheLanguage(): void
    {
        self::assertSame([100, 4096], [new Limits()->repetitions, new Limits()->text]);
    }

    public function testCountsWrittenInCodeBelowOneCountNothing(): void
    {
        $repeat = new Pool(new FunctionCall(MathFunction::Repeat, new Dice(1, new StandardDie(6)), new Constant(-1)), new Dice(1, new StandardDie(6)));
        new Limits(dice: 1)->check($repeat);

        $repetitions = new Pool(new FunctionCall(MathFunction::Repeat, new FunctionCall(MathFunction::Repeat, new Constant(1), new Constant(1)), new Constant(-1)));
        new Limits(repetitions: 1)->check($repetitions);

        $none = new Pool(new FunctionCall(MathFunction::Repeat, new Dice(1, new StandardDie(6)), new Constant(0)));
        new Limits(dice: 1)->check($none);

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 1, 2));
        new Limits(dice: 1)->check(new Pool(new FunctionCall(MathFunction::Repeat, new Dice(1, new StandardDie(6)), new Constant(1)), new Dice(1, new StandardDie(6))));
    }

    public function testAGermanNotationNamesItsCustomDice(): void
    {
        $parser = new DiceParser(notation: new Notation('W'));

        self::assertSame('3d{x}, 1L[a]', $parser->parse('3W{x}; L[a]')->canonical());
    }

    public function testTheLimitsCountTheNewConstructs(): void
    {
        self::assertSame('{2d6, 2d6}', new DiceParser(new Limits(dice: 4))->parse('{2d6, 2d6}')->canonical());
        self::assertSame('repeat(2d6, 2)', new DiceParser(new Limits(dice: 4))->parse('repeat(2d6, 2)')->canonical());
        self::assertSame('repeat(2d6, 1d2)', new DiceParser(new Limits(dice: 3))->parse('repeat(2d6, 1d2)')->canonical());
        self::assertSame('4L[a]', new DiceParser(new Limits(dice: 4))->parse('4L[a]')->canonical());
        self::assertSame('(1d4)L[a]', new DiceParser(new Limits(dice: 1))->parse('(1d4)L[a]')->canonical());
        self::assertSame('1d2i[1]{1d2}{1d2}', new DiceParser(new Limits(dice: 3))->parse('1d2i[1]{1d2}{1d2}')->canonical());
        self::assertSame('1d2S[1]{1d2}{1d2}', new DiceParser(new Limits(dice: 3))->parse('1d2S[1]{1d2}{1d2}')->canonical());
        self::assertSame('repeat(repeat(1, 10), 9)', new DiceParser(new Limits(repetitions: 99))->parse('repeat(repeat(1, 10), 9)')->canonical());
        self::assertSame('repeat(1, 1d6)', new DiceParser(new Limits(repetitions: 1))->parse('repeat(1, 1d6)')->canonical());
    }

    /**
     * @return iterable<string, array{Limits, string, string}>
     */
    public static function limitsPassed(): iterable
    {
        yield 'the dice of a group' => [new Limits(dice: 3), '{2d6, 2d6}', 'The dice of a roll are limited to 3; this asks for 4.'];
        yield 'the dice repeated' => [new Limits(dice: 3), 'repeat(2d6, 2)', 'The dice of a roll are limited to 3; this asks for 4.'];
        yield 'the dice repeated and their count' => [new Limits(dice: 2), 'repeat(2d6, 1d2)', 'The dice of a roll are limited to 2; this asks for 3.'];
        yield 'the items of a list' => [new Limits(dice: 3), '4L[a]', 'The dice of a roll are limited to 3; this asks for 4.'];
        yield 'the count of a list' => [new Limits(dice: 1), '(2d4)L[a]', 'The dice of a roll are limited to 1; this asks for 2.'];
        yield 'every branch of an if' => [new Limits(dice: 2), '1d2i[1]{1d2}{1d2}', 'The dice of a roll are limited to 2; this asks for 3.'];
        yield 'every branch of a switch' => [new Limits(dice: 2), '1d2S[1]{1d2}{1d2}', 'The dice of a roll are limited to 2; this asks for 3.'];
        yield 'every case of a switch' => [new Limits(dice: 2), '1d2S[1]{1d2}[2]{1d2}', 'The dice of a roll are limited to 2; this asks for 3.'];
        yield 'the repetitions' => [new Limits(repetitions: 2), 'repeat(1, 3)', 'The repetitions of a roll are limited to 2; this asks for 3.'];
        yield 'the repetitions within repetitions' => [new Limits(repetitions: 98), 'repeat(repeat(1, 10), 9)', 'The repetitions of a roll are limited to 98; this asks for 99.'];
        yield 'the repetitions of every instruction' => [new Limits(repetitions: 3), 'repeat(1, 2); repeat(1, 2)', 'The repetitions of a roll are limited to 3; this asks for 4.'];
        yield 'the repetitions of a branch' => [new Limits(repetitions: 1), '1i[1]{repeat(1, 2)}', 'The repetitions of a roll are limited to 1; this asks for 2.'];
        yield 'repetitions past integers' => [new Limits(length: 1000), str_repeat('repeat(', 3).'1d6'.str_repeat(', 1000000000)', 3), \sprintf('The repetitions of a roll are limited to 100; this asks for %d.', \PHP_INT_MAX)];
        yield 'the instructions' => [new Limits(expressions: 2), '1; 2; 3', 'The expressions of a pool are limited to 2; this asks for 3.'];
    }

    #[DataProvider('limitsPassed')]
    public function testAnInputPastALimitIsRefused(Limits $limits, string $input, string $message): void
    {
        $this->expectExceptionObject(new LimitExceeded($message));

        new DiceParser($limits)->parse($input);
    }

    public function testDiceCountedPastIntegersStopAtTheLargest(): void
    {
        $this->expectExceptionObject(new LimitExceeded(\sprintf('The dice of a roll are limited to 100; this asks for %d.', \PHP_INT_MAX)));

        new DiceParser(new Limits(length: 1000, repetitions: \PHP_INT_MAX))->parse(str_repeat('repeat(', 3).'1d6'.str_repeat(', 1000000000)', 3));
    }

    public function testTheLexerReadsTextsCommentsAndOtherCharacters(): void
    {
        self::assertSame(
            [['Text', 'a\"b', 0], ['Comment', 'c', 7], ['Symbol', ';', 10], ['Comment', 'd', 11], ['Other', 'é', 15], ['Symbol', '{', 16], ['Symbol', '$', 17], ['Symbol', '}', 18], ['End', '', 19]],
            array_map(static fn (Token $token): array => [$token->type->name, $token->text, $token->position], Lexer::tokenize("\"a\\\"b\" #c\n;//d\né{\$}")),
        );
        self::assertSame([['Letter', 'W'], ['Name', 'x'], ['Letter', 'd'], ['Name', 'y'], ['Letter', 'e'], ['Symbol', '{']], array_map(static fn (Token $token): array => [$token->type->name, $token->text], \array_slice(Lexer::tokenize('W{x}d{y}e{', 'w'), 0, 6)));
        self::assertSame([['Symbol', '/'], ['Number', '2']], array_map(static fn (Token $token): array => [$token->type->name, $token->text], \array_slice(Lexer::tokenize('/ /2'), 1, 2)));
        self::assertSame(['Comment', ''], [Lexer::tokenize('#')[0]->type->name, Lexer::tokenize('#')[0]->text]);
        self::assertSame(['Text', '\\\\'], [Lexer::tokenize('"\\\\"')[0]->type->name, Lexer::tokenize('"\\\\"')[0]->text]);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'a reference to no instruction' => [static fn (): Reference => new Reference(0), 'Instructions are numbered from 1; 0 is not one.'];
        yield 'a reference to a negative item' => [static fn (): Reference => new Reference(1, -1), 'Items are numbered from 0; -1 is not one.'];
        yield 'dice of no instruction' => [static fn (): InstructionDice => new InstructionDice([]), 'Dice are taken from one instruction or more.'];
        yield 'dice of instruction 0' => [static fn (): InstructionDice => new InstructionDice([1, 0]), 'Instructions are numbered from 1; 0 is not one.'];
        yield 'dice of an instruction twice' => [static fn (): InstructionDice => new InstructionDice([2, 2]), 'An instruction gives its dice once.'];
        yield 'dice of instructions rerolled' => [static fn (): InstructionDice => new InstructionDice([1], new Modifiers(new Reroll(new Comparison(Comparator::Equal, 1)))), 'The dice of instructions before are not rolled again: they take no reroll and no explosion.'];
        yield 'an empty group' => [static fn (): Group => new Group([]), 'A group holds at least one expression.'];
        yield 'a group dropping all' => [static fn (): Group => new Group([new Constant(1), new Constant(2)], new Modifiers(selection: new Selection(SelectionMode::DropLowest, 2))), '2 dice cannot keep 2, nor drop all of them.'];
        yield 'an item without text' => [static fn (): ListItem => new ListItem("\t"), 'An item of a list has a text.'];
        yield 'an item weighing nothing' => [static fn (): ListItem => new ListItem('a', 0), 'An item of a list weighs at least 1; 0 does not.'];
        yield 'an item on a range that weighs' => [static fn (): ListItem => new ListItem('a', 2, new Range(1, 1)), 'An item covers a range or weighs, not both.'];
        yield 'an empty list' => [static fn (): TextList => new TextList(new Constant(1), []), 'A list holds at least one item.'];
        yield 'a list of ranges not in order' => [static fn (): TextList => new TextList(new Constant(1), [new ListItem('a', range: new Range(3, 4)), new ListItem('b', range: new Range(1, 2))]), 'The ranges of a list follow each other: 1..2 comes after 4.'];
        yield 'a list of a range after none' => [static fn (): TextList => new TextList(new Constant(1), [new ListItem('a'), new ListItem('b', range: new Range(1, 2))]), 'The items of a list all cover a range, or none does.'];
        yield 'a list drawing a negative count' => [static fn (): TextList => new TextList(new Constant(-1), [new ListItem('a')]), 'A list draws at least one item; -1 is not.'];
        yield 'a placeholder of a result without its instruction' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Result), 'The placeholder $ names its instruction.'];
        yield 'a placeholder of the dice without their instruction' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Dice), 'The placeholder @ names its instruction.'];
        yield 'a placeholder of the subject with an instruction' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Subject, 1), 'The placeholder %1 names no instruction.'];
        yield 'a placeholder of instruction 0' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Result, 0), 'Instructions are numbered from 1; 0 is not one.'];
        yield 'a placeholder of the dice with an item' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Dice, 1, 0), 'Only "$" takes an item, numbered from 0.'];
        yield 'a placeholder of a negative item' => [static fn (): Placeholder => new Placeholder(PlaceholderKind::Result, 1, -1), 'Only "$" takes an item, numbered from 0.'];
        yield 'a switch without a case' => [static fn (): Choice => new Choice(new Constant(1), []), 'A switch has at least one case.'];
        yield 'a case judging each die' => [static fn (): SwitchCase => new SwitchCase(new Condition(new Comparison(Comparator::Equal, 1), Scope::Die), new Constant(1)), 'A branch judges its subject as a whole: its value, all or any of its dice, or their total; the scope "die" judges each die.'];
        yield 'an if judging each die' => [static fn (): Conditional => new Conditional(new Constant(1), new Condition(new Comparison(Comparator::Equal, 1), Scope::Die), new Constant(1)), 'A branch judges its subject as a whole: its value, all or any of its dice, or their total; the scope "die" judges each die.'];
    }

    /**
     * @param Closure(): mixed $definition
     */
    #[DataProvider('invalidDefinitions')]
    public function testAnInvalidDefinitionIsRefused(Closure $definition, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $definition();
    }

    public function testTheNewNodesKeepWhatTheyAreGiven(): void
    {
        $dice = new InstructionDice([1, 3], merge: true);
        $list = new TextList(new Constant(2), [new ListItem('a'), new ListItem('b', 3)], true, true);
        $ranged = new TextList(new Constant(1), [new ListItem('a', range: new Range(-1, 0)), new ListItem('b', range: new Range(1, 1))]);
        $choice = new Choice(new Constant(1), [new SwitchCase(new Condition(new Comparison(Comparator::Equal, 1)), new Constant(2))], new Constant(3), true);
        $placeholder = new Placeholder(PlaceholderKind::Before);

        self::assertSame([[1, 3], true, [true, true]], [$dice->instructions, $dice->merge, [$list->unique, $list->joined]]);
        self::assertSame([false, false], [new InstructionDice([1])->merge, new TextList(new Constant(1), [new ListItem('a')])->unique || new TextList(new Constant(1), [new ListItem('a')])->joined]);
        self::assertSame([true, 3], [$choice->exclusive, $choice->default instanceof Constant ? $choice->default->value : null]);
        self::assertSame([3, 'b', 1, null], [$list->items[1]->weight, $list->items[1]->text, $ranged->items[1]->range?->from, $list->items[0]->range]);
        self::assertSame([1, 3, 2, 1], [$list->items[0]->chances(), $list->items[1]->chances(), $ranged->items[0]->chances(), $ranged->items[1]->chances()]);
        self::assertSame(['ab'], new Sentence(['a', '', 'b'])->parts);
        self::assertSame(['ab', 'c'], [new Sentence(['a', 'b', new Placeholder(PlaceholderKind::Before), 'c'])->parts[0], new Sentence(['a', 'b', new Placeholder(PlaceholderKind::Before), 'c'])->parts[2]]);
        self::assertSame([$placeholder], new Sentence(['a', $placeholder])->placeholders());
        self::assertFalse(new Choice(new Constant(1), $choice->cases)->exclusive);
        self::assertNull(new Choice(new Constant(1), $choice->cases)->default);
        self::assertSame([[], [0]], [new Sentence([])->parts, array_keys(new Sentence(['', new Placeholder(PlaceholderKind::Before)])->parts)]);
        self::assertSame([true, true, false, false, false], array_map(static fn (PlaceholderKind $kind): bool => $kind->namesInstruction(), [PlaceholderKind::Result, PlaceholderKind::Dice, PlaceholderKind::Subject, PlaceholderKind::SubjectDice, PlaceholderKind::Before]));
        new TextList(new Constant(2), [new ListItem('a'), new ListItem('b')], true)->checkCount(2);
        $this->expectExceptionObject(InvalidDefinition::because('3 different items cannot be drawn from 2.'));
        new TextList(new Constant(2), [new ListItem('a'), new ListItem('b')], true)->checkCount(3);
    }
}
