<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Parser\Lexer;
use Tilttek\Dice\Result\DieOrigin;
use Tilttek\Dice\Result\NodeResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Rolling\Context;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\Dice\Rolling\SetRoller;

/**
 * Properties of the parser over generated expressions, each drawn from a fixed seed so that a failure is replayed by
 * its seed: whatever the input, the parser and the roller refuse with the library's own exceptions or stay within
 * their limits, and what parses is written in a form that parses back to itself.
 */
#[CoversClass(DiceParser::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Roller::class)]
#[CoversClass(SetRoller::class)]
#[CoversClass(Context::class)]
#[CoversClass(NotationWriter::class)]
final class ParserPropertyTest extends TestCase
{
    /** The generated expressions per seed. */
    private const int CASES = 150;

    /** The rolls of each expression that parses. */
    private const int ROLLS = 4;

    /** The modifiers the generator puts after a set of dice, `%d` a number it draws. */
    private const array MODIFIERS = ['kh%d', 'kl%d', 'dh%d', 'dl%d', '!', '!!', '!p', '!(%d)', 'r1', 'ro1', 'r<%d', 'g%d', 'o%d', 'c[>=%d]', 'f[>%d]', '>=%d', 'mi%d', 'ma%d', 'u', 's'];

    /** The characters of the inputs drawn at random, operators and letters of the notation included. */
    private const string ALPHABET = '0123456789d+-*/%()[]{}!<>=,;:@#khlrogcfpumsaxF. ';

    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([1, 7, 53, 2026] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    /**
     * A generated expression parses within the limits, or is refused with `InvalidExpression` or `LimitExceeded`;
     * once parsed, every roll of it stays within the dice, explosions, rerolls and sides of the limits, or is refused
     * with a `DiceException`.
     */
    #[DataProvider('seeds')]
    public function testTheBudgetsAreAlwaysRespected(int $seed): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $limits = new Limits(dice: 20, sides: 50, explosions: 10, expressions: 3, length: 64, rerolls: 10, repetitions: 10);
        $dice = new DiceRoller($limits);
        $parsed = 0;

        for ($case = 0; $case < self::CASES; ++$case) {
            $input = $this->expression($randomizer, 0);

            try {
                $pool = $dice->parse($input);
            } catch (InvalidExpression|LimitExceeded) {
                continue;
            }

            ++$parsed;
            self::assertLessThanOrEqual(64, mb_strlen($input), $input);
            self::assertLessThanOrEqual(3, \count($pool->expressions), $input);
            $limits->check($pool);

            for ($roll = 0; $roll < self::ROLLS; ++$roll) {
                $this->assertRollWithinLimits($dice, $pool, $input, $seed * 1_000 + $roll);
            }
        }

        // The generator gives expressions that parse, not only refusals.
        self::assertGreaterThan(self::CASES / 4, $parsed);
    }

    /**
     * Any input of the notation's characters is parsed or refused with a `DiceException`, never another error, and an
     * input longer than the limit is refused for its length.
     */
    #[DataProvider('seeds')]
    public function testAnyInputIsParsedOrRefusedByTheLibrary(int $seed): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $dice = new DiceRoller(new Limits(length: 48));

        for ($case = 0; $case < self::CASES * 2; ++$case) {
            $input = '';

            for ($length = $randomizer->getInt(1, 60); \strlen($input) < $length;) {
                $input .= self::ALPHABET[$randomizer->getInt(0, \strlen(self::ALPHABET) - 1)];
            }

            try {
                $dice->parse($input);
                self::assertLessThanOrEqual(48, mb_strlen($input), $input);
            } catch (LimitExceeded $limitExceeded) {
                if (mb_strlen($input) > 48) {
                    self::assertSame(\sprintf('The characters of an expression are limited to 48; this asks for %d.', mb_strlen($input)), $limitExceeded->getMessage());
                }
            } catch (DiceException $diceException) {
                self::assertInstanceOf(InvalidExpression::class, $diceException, $input);
                self::assertLessThanOrEqual(48, mb_strlen($input), $input);
            }
        }
    }

    /**
     * What parses is written in a canonical form that parses back to the same form and rolls the same totals from the
     * same seed.
     */
    #[DataProvider('seeds')]
    public function testTheCanonicalFormParsesBackToItself(int $seed): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $dice = new DiceRoller(new Limits(dice: 20, sides: 50, explosions: 10, rerolls: 10));

        for ($case = 0; $case < self::CASES; ++$case) {
            $input = $this->expression($randomizer, 0);

            try {
                $pool = $dice->parse($input);
            } catch (DiceException) {
                continue;
            }

            $written = $dice->write($pool);
            $again = $dice->parse($written);
            self::assertSame($written, $dice->write($again), $input);

            try {
                $first = $dice->roll($pool, new Randomizer(new Xoshiro256StarStar($case)))->totals();
            } catch (DiceException) {
                continue;
            }

            self::assertSame($first, $dice->roll($again, new Randomizer(new Xoshiro256StarStar($case)))->totals(), $input.' → '.$written);
        }
    }

    private function assertRollWithinLimits(DiceRoller $dice, Pool $pool, string $input, int $seed): void
    {
        try {
            $result = $dice->roll($pool, new Randomizer(new Xoshiro256StarStar($seed)));
        } catch (DiceException) {
            return;
        }

        $initial = 0;
        $explosions = 0;
        $rerolls = 0;

        foreach ($result->results as $expression) {
            foreach ($this->sets($expression->result) as $die) {
                match ($die->origin) {
                    DieOrigin::Initial => ++$initial,
                    DieOrigin::Explosion => ++$explosions,
                    DieOrigin::Reroll => ++$rerolls,
                };
                // A die that compounds its explosions holds one roll for each.
                $explosions += \count($die->rolls) - 1;

                foreach ($die->rolls as $roll) {
                    self::assertLessThanOrEqual(50, $roll, $input);
                }
            }
        }

        self::assertLessThanOrEqual(20, $initial, $input);
        self::assertLessThanOrEqual(10, $explosions, $input);
        self::assertLessThanOrEqual(10, $rerolls, $input);
    }

    /**
     * @return list<RolledDie>
     */
    private function sets(NodeResult $result): array
    {
        $dice = [];

        foreach ($result->sets() as $set) {
            array_push($dice, ...$set->dice);
        }

        return $dice;
    }

    /**
     * An expression of the notation: sets of dice with their modifiers, numbers, and their sums, differences and
     * products, sometimes in parentheses; the counts and sides sometimes past the limits.
     */
    private function expression(Randomizer $randomizer, int $depth): string
    {
        $expression = $this->term($randomizer, $depth);

        for ($terms = $randomizer->getInt(0, 2); $terms > 0; --$terms) {
            $expression .= ['+', '-', '*'][$randomizer->getInt(0, 2)].$this->term($randomizer, $depth);
        }

        return $expression;
    }

    private function term(Randomizer $randomizer, int $depth): string
    {
        $kind = $randomizer->getInt(0, 9);

        if ($kind < 2) {
            return (string) $randomizer->getInt(0, 30);
        }

        if (9 === $kind && $depth < 2) {
            return '('.$this->expression($randomizer, $depth + 1).')';
        }

        $count = $randomizer->getInt(0, 9) < 8 ? $randomizer->getInt(1, 8) : $randomizer->getInt(9, 40);
        $sides = $randomizer->getInt(0, 9) < 8 ? [2, 4, 6, 8, 10, 12, 20][$randomizer->getInt(0, 6)] : $randomizer->getInt(1, 120);
        $set = $count.'d'.$sides;

        for ($modifiers = $randomizer->getInt(0, 2); $modifiers > 0; --$modifiers) {
            $set .= \sprintf(self::MODIFIERS[$randomizer->getInt(0, \count(self::MODIFIERS) - 1)], $randomizer->getInt(1, 12));
        }

        return $set;
    }
}
