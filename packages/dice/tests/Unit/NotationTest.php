<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Composite;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\Junction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Modulo;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\Range;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Notation\ExpressionWriter;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Parser\DiceParser;

#[CoversClass(Notation::class)]
#[CoversClass(Notations::class)]
#[CoversClass(NotationWriter::class)]
#[CoversClass(ExpressionWriter::class)]
final class NotationTest extends TestCase
{
    public function testANotationKeepsItsLettersAndWords(): void
    {
        $notation = new Notation('W', 'Vor', 'nach');

        self::assertSame(['W', 'Vor', 'nach'], [$notation->die, $notation->advantage, $notation->disadvantage]);
        self::assertSame(['d', 'adv', 'dis'], [new Notation()->die, new Notation()->advantage, new Notation()->disadvantage]);
        self::assertSame(str_repeat('a', 16), new Notation('n', str_repeat('a', 16))->advantage);
        self::assertSame('ab', new Notation('n', disadvantage: 'ab')->disadvantage);
    }

    public function testADieLetterIsReadInEitherCaseWithTheCanonicalOne(): void
    {
        $notation = new Notation('W');

        self::assertSame([true, true, true, true, false], [$notation->isDie('d'), $notation->isDie('D'), $notation->isDie('W'), $notation->isDie('w'), $notation->isDie('t')]);
        self::assertTrue(new Notation('t')->isDie('T'));
    }

    /**
     * @return iterable<string, array{callable(): Notation, string}>
     */
    public static function invalidNotations(): iterable
    {
        yield 'two letters' => [static fn (): Notation => new Notation('dd'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "dd" is not.'];
        yield 'a digit' => [static fn (): Notation => new Notation('1'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "1" is not.'];
        yield 'a letter and a newline' => [static fn (): Notation => new Notation("w\n"), "The letter of a die is one letter other than b, f, h, i, k, l, m or r; \"w\n\" is not."];
        yield 'a modifier letter' => [static fn (): Notation => new Notation('k'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "k" is not.'];
        yield 'a modifier letter in capitals' => [static fn (): Notation => new Notation('R'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "R" is not.'];
        yield 'the letter of a merge' => [static fn (): Notation => new Notation('M'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "M" is not.'];
        yield 'the letter of a bind' => [static fn (): Notation => new Notation('b'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "b" is not.'];
        yield 'the letter of an if' => [static fn (): Notation => new Notation('i'), 'The letter of a die is one letter other than b, f, h, i, k, l, m or r; "i" is not.'];
        yield 'a short word' => [static fn (): Notation => new Notation('d', 'a'), 'A word of advantage or disadvantage is 2 to 16 letters; "a" is not.'];
        yield 'a long word' => [static fn (): Notation => new Notation('d', 'adv', str_repeat('b', 17)), \sprintf('A word of advantage or disadvantage is 2 to 16 letters; "%s" is not.', str_repeat('b', 17))];
        yield 'a word and a newline' => [static fn (): Notation => new Notation('d', "adv\n"), "A word of advantage or disadvantage is 2 to 16 letters; \"adv\n\" is not."];
        yield 'the same word' => [static fn (): Notation => new Notation('d', 'Same', 'same'), 'Advantage and disadvantage need two different words.'];
        yield 'the same word, capitalised second' => [static fn (): Notation => new Notation('d', 'same', 'Same'), 'Advantage and disadvantage need two different words.'];
    }

    /**
     * @param callable(): Notation $notation
     */
    #[DataProvider('invalidNotations')]
    public function testAnInvalidNotationIsRefused(callable $notation, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));
        $notation();
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function locales(): iterable
    {
        yield 'none' => [null, 'd'];
        yield 'German' => ['de', 'W'];
        yield 'Austrian German' => ['de_AT', 'W'];
        yield 'Swiss German, with a dash' => ['de-CH', 'W'];
        yield 'German in capitals' => ['DE', 'W'];
        yield 'Danish' => ['da', 'T'];
        yield 'Norwegian Bokmål' => ['nb_NO', 'T'];
        yield 'Norwegian Nynorsk' => ['nn', 'T'];
        yield 'Norwegian' => ['no', 'T'];
        yield 'Swedish' => ['sv_SE', 'T'];
        yield 'French' => ['fr_CA', 'd'];
    }

    #[DataProvider('locales')]
    public function testEachLanguageHasItsLetter(?string $locale, string $letter): void
    {
        self::assertSame($letter, new Notations()->for($locale)->die);
    }

    public function testAnApplicationAddsAndReplacesNotations(): void
    {
        $notations = new Notations(['de' => new Notation('X'), 'pt-BR' => new Notation('Q')]);

        self::assertSame(['Y', 'X'], [new Notations(['de' => new Notation('X'), 'de_ch' => new Notation('Y')])->for('de-CH')->die, new Notations(['de' => new Notation('X'), 'de_ch' => new Notation('Y')])->for('de_AT')->die]);
        self::assertSame(['X', 'X', 'Q', 'd', 'T'], [$notations->for('de_DE')->die, $notations->for('de')->die, $notations->for('pt_br')->die, $notations->for('pt')->die, $notations->for('sv')->die]);
    }

    public function testAPoolIsWrittenWithALanguagesLetter(): void
    {
        $pool = new DiceParser()->parse('2d6+1, 4dF, d{coin}, 4d6r1!kh3>=4');

        self::assertSame('2W6+1, 4WF, 1W{coin}, 4W6r1!kh3>=4', new NotationWriter(new Notation('W'))->write($pool));
        self::assertSame('2d6+1, 4dF, 1d{coin}, 4d6r1!kh3>=4', new NotationWriter()->write($pool));
    }

    /**
     * @return iterable<string, array{Node, string}>
     */
    public static function trees(): iterable
    {
        yield 'a negative constant' => [new Constant(-3), '-3'];
        yield 'subtracting a negative constant' => [new Operation(Operator::Subtract, new Constant(1), new Constant(-3)), '1--3'];
        yield 'multiplying a negative constant' => [new Operation(Operator::Multiply, new Constant(-3), new Constant(2)), '-3*2'];
        yield 'negating a negative constant' => [new Negation(new Constant(-3)), '--3'];
        yield 'negating a negation' => [new Negation(new Negation(new Constant(3))), '--3'];
        yield 'negating a product' => [new Negation(new Operation(Operator::Multiply, new Constant(2), new Constant(3))), '-(2*3)'];
        yield 'a sum times a constant' => [new Operation(Operator::Multiply, new Operation(Operator::Add, new Constant(1), new Constant(2)), new Constant(3)), '(1+2)*3'];
        yield 'a product plus a constant' => [new Operation(Operator::Add, new Operation(Operator::Multiply, new Constant(1), new Constant(2)), new Constant(3)), '1*2+3'];
        yield 'a constant plus a product' => [new Operation(Operator::Add, new Constant(3), new Operation(Operator::Multiply, new Constant(1), new Constant(2))), '3+1*2'];
        yield 'a constant minus a sum' => [new Operation(Operator::Subtract, new Constant(3), new Operation(Operator::Add, new Constant(1), new Constant(2))), '3-(1+2)'];
        yield 'a product times a negation' => [new Operation(Operator::Multiply, new Constant(2), new Negation(new Constant(2))), '2*-2'];
        yield 'a reroll on a negative face' => [new Dice(4, new FudgeDie(), new Modifiers(reroll: new Reroll(new Comparison(Comparator::Equal, -1)))), '4dFr=-1'];
        yield 'an explosion on face zero' => [new Dice(4, new FudgeDie(), new Modifiers(explosion: new Explosion(new Comparison(Comparator::Equal, 0)))), '4dF!0'];
        yield 'an explosion above a negative face' => [new Dice(4, new FudgeDie(), new Modifiers(explosion: new Explosion(new Comparison(Comparator::Greater, -1)))), '4dF!>-1'];
        yield 'a power of a negative constant' => [new Operation(Operator::Power, new Constant(-2), new Constant(2)), '(-2)^2'];
        yield 'a power of zero' => [new Operation(Operator::Power, new Constant(0), new Constant(2)), '0^2'];
        yield 'a power to a negative constant' => [new Operation(Operator::Power, new Constant(2), new Constant(-2)), '2^-2'];
        yield 'a power to a sum' => [new Operation(Operator::Power, new Constant(2), new Operation(Operator::Add, new Constant(1), new Constant(1))), '2^(1+1)'];
        yield 'a power of a power' => [new Operation(Operator::Power, new Operation(Operator::Power, new Constant(2), new Constant(3)), new Constant(2)), '(2^3)^2'];
        yield 'a negated power' => [new Negation(new Operation(Operator::Power, new Constant(2), new Constant(2))), '-2^2'];
        yield 'a count of a negative constant' => [new ComputedDice(new Constant(-1), new StandardDie(6)), '(-1)d6'];
        yield 'a count of zero' => [new ComputedDice(new Constant(0), new StandardDie(6)), '0d6'];
        yield 'a failure on a negative face' => [new Dice(4, new FudgeDie(), new Modifiers(success: new Condition(new Comparison(Comparator::Equal, 1)), failure: new Comparison(Comparator::Equal, -1))), '4dF=1f=-1'];
        yield 'successes of a die as it fell' => [new Dice(4, new FudgeDie(), new Modifiers(success: new Condition(new Comparison(Comparator::Equal, 1), Scope::Die))), '4dFc[die:1]'];
        yield 'successes of a value unlike' => [new Dice(4, new FudgeDie(), new Modifiers(success: new Condition(new Comparison(Comparator::NotEqual, 1)))), '4dFc[!=1]'];
        yield 'successes of a range' => [new Dice(4, new FudgeDie(), new Modifiers(success: new Condition(new Range(0, 1)))), '4dFc[0..1]'];
        yield 'a critical mark on a negative face' => [new Dice(4, new FudgeDie(), new Modifiers(criticalFailure: new Critical(new Comparison(Comparator::Equal, -1)))), '4dFcf=-1'];
        yield 'a critical mark on a value unlike' => [new Dice(4, new FudgeDie(), new Modifiers(criticalSuccess: new Critical(new Comparison(Comparator::NotEqual, 0)))), '4dFcs[!=0]'];
        yield 'a composite on the right' => [new Dice(4, new FudgeDie(), new Modifiers(filter: new Condition(new Composite(Junction::Or, new Comparison(Comparator::Equal, 1), new Composite(Junction::Xor, new Comparison(Comparator::Equal, 0), new Comparison(Comparator::Equal, -1)))))), '4dFf[1|(0^-1)]'];
        yield 'a conjunction of disjunctions' => [new Dice(4, new FudgeDie(), new Modifiers(filter: new Condition(new Composite(Junction::And, new Composite(Junction::Or, new Comparison(Comparator::Equal, 1), new Comparison(Comparator::Equal, 0)), new Comparison(Comparator::GreaterOrEqual, 0)), Scope::All))), '4dFf[all:(1|0)&>=0]'];
        yield 'a remainder unlike' => [new Dice(4, new StandardDie(6), new Modifiers(success: new Condition(new Modulo(2, new Comparison(Comparator::NotEqual, 0))))), '4d6c[%2!=0]'];
    }

    #[DataProvider('trees')]
    public function testATreeIsWrittenWithTheParenthesesItNeeds(Node $tree, string $text): void
    {
        self::assertSame($text, new NotationWriter()->writeNode($tree));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function roundTrips(): iterable
    {
        foreach (['4d6kh3', '(1d8+2)*2', '1-(2-3)', '-(1d4*2)+3/4', '10/(3*2)', '4dFr=-1!>=1dl1<0', '3d{coin}', '--1', '2*-2', '1d6!kl1, 2d10>=7', '4d[-1..1]', '(1d4+1)d6', '3d(2*6)', '4dF.1', '[10,25,43,8]kh1', '4d6min3max5', '3d10!!', '2d6!!p6(2)', '3d10!(1d4)', '4d6r<3', '4d6ro[1|2]', '4d6uo', '2d6tt[1:0]', '4d6f[!=6]', '6d10>=7f1', '8d10>=6-@=1', '10d10o2[>=7]', '5d10g10', '5d10t[die:>5:15,<=5:0]', '1d20cs>19cf1', '8d10look[2:accent,muted]', '4d6c[any:6]', 'floor(1d6/3)+ceil(1d6/3)', 'min(1d6, 3)+abs(-2)', '2^3^2', '-2^2', '10%3*2', '1d6sd', '4dFc[-1..1]', '4d6u', '(1d4)d6kh1', '3d(1d6)!', '4d6c[1|2&3]', '4d6c[(1|2)&3]', '4d6c[1&(2|3)]'] as $expression) {
            yield $expression => [$expression];
        }
    }

    #[DataProvider('roundTrips')]
    public function testTheCanonicalFormParsesBackToItself(string $expression): void
    {
        $parser = new DiceParser();
        $canonical = $parser->parse($expression)->canonical();

        self::assertSame($expression, $canonical);
        self::assertEquals($parser->parse($expression), $parser->parse($canonical));
    }

    public function testANodeOrADieOfAnotherLibraryIsRefused(): void
    {
        $node = new class implements Node {};

        try {
            new NotationWriter()->writeNode($node);
            self::fail('The node was written.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame(\sprintf("The node %s is not one of the library's.", $node::class), $invalidDefinition->getMessage());
        }

        $validator = new class implements Validator {
            #[Override]
            public function matches(int $value): bool
            {
                return true;
            }

            #[Override]
            public function matchesAll(int $lowest, int $highest): bool
            {
                return false;
            }
        };

        try {
            new NotationWriter()->writeValidator($validator);
            self::fail('The validator was written.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame(\sprintf('%s is not a validator the library writes.', $validator::class), $invalidDefinition->getMessage());
        }

        $die = new class implements DieType {};
        $this->expectExceptionObject(InvalidDefinition::because(\sprintf('%s is not a die the library writes.', $die::class)));
        new NotationWriter()->write(new Pool(new Dice(1, $die)));
    }

    public function testAValidatorIsWrittenAsBetweenBrackets(): void
    {
        self::assertSame(['6', '-1', '>=2', '!=3', '1..3', '%2=0', '1|2&3'], array_map(new NotationWriter()->writeValidator(...), [
            new Comparison(Comparator::Equal, 6),
            new Comparison(Comparator::Equal, -1),
            new Comparison(Comparator::GreaterOrEqual, 2),
            new Comparison(Comparator::NotEqual, 3),
            new Range(1, 3),
            new Modulo(2, new Comparison(Comparator::Equal, 0)),
            new Composite(Junction::Or, new Comparison(Comparator::Equal, 1), new Composite(Junction::And, new Comparison(Comparator::Equal, 2), new Comparison(Comparator::Equal, 3))),
        ]));
    }
}
