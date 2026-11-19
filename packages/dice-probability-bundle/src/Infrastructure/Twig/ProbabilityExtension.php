<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Infrastructure\Twig;

use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exception\CannotCompute;
use Tilttek\DiceProbability\Versus;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\Exception\TooManyOutcomes;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The probabilities in templates: `tilttek_dice_distribution()` and `tilttek_dice_versus()` give the library's
 * results, `tilttek_dice_probabilities()` draws them as the bundle's table and chart, the `tilttek_dice_fraction`
 * filter writes a probability in its lowest terms within a bounded work, and `tilttek_dice_probability_kit()` names a component of the UX
 * kit the templates draw with.
 */
final readonly class ProbabilityExtension
{
    public const string TEMPLATE = '@TilttekDiceProbability/probabilities.html.twig';

    /** The longest denominator written as a fraction: past it, the percentage alone says the probability. */
    public const int FRACTION_DIGITS = 12;

    /** The most digits a fraction may be asked to be written with. */
    public const int MAX_FRACTION_DIGITS = 60;

    /** The longest terms reduced on native integers: past them, ext-gmp reduces them, or they are not. */
    public const int NATIVE_DIGITS = 18;

    private bool $gmp;

    /**
     * @param bool $gmp whether ext-gmp may reduce the long fractions; it does only when it is loaded
     */
    public function __construct(
        private DiceStatistics $statistics,
        private KitComponents $kit = new KitComponents(),
        bool $gmp = true,
    ) {
        $this->gmp = $gmp && \extension_loaded('gmp');
    }

    /**
     * @param ?string $rounding floor, ceil, round, towards_zero or away_from_zero; the configured rounding when null
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws TooManyOutcomes
     */
    #[AsTwigFunction('tilttek_dice_distribution')]
    public function distribution(string $expression, ?string $locale = null, ?string $rounding = null): Distribution
    {
        return $this->statistics->distribution($expression, $locale, null === $rounding ? null : Rounding::from($rounding));
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws TooManyOutcomes
     */
    #[AsTwigFunction('tilttek_dice_versus')]
    public function versus(string $first, string $second, ?string $locale = null, ?string $rounding = null): Versus
    {
        return $this->statistics->compare($first, $second, $locale, null === $rounding ? null : Rounding::from($rounding));
    }

    /**
     * The table and the chart of an expression, and of a second one against it when given.
     *
     * @param string $show what the chart draws: exactly, at_least or at_most
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws TooManyOutcomes
     */
    #[AsTwigFunction('tilttek_dice_probabilities', needsEnvironment: true, isSafe: ['html'])]
    public function probabilities(Environment $twig, string $expression, ?string $versus = null, string $show = 'exactly', ?string $locale = null, ?string $rounding = null): string
    {
        $mode = null === $rounding ? null : Rounding::from($rounding);
        $distribution = $this->statistics->distribution($expression, $locale, $mode);

        return $twig->render(self::TEMPLATE, [
            'expression' => $this->statistics->canonical($expression, $locale),
            'distribution' => $distribution,
            'versus' => null === $versus ? null : new Versus($distribution, $this->statistics->distribution($versus, $locale, $mode)),
            'second' => null === $versus ? null : $this->statistics->canonical($versus, $locale),
            'show' => $show,
        ]);
    }

    /**
     * The name of a component of the UX kit, `Card` or `Table:Row`, as the application installed it: the templates
     * call `{% component (tilttek_dice_probability_kit('Card')) %}`.
     */
    #[AsTwigFunction('tilttek_dice_probability_kit')]
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }

    /**
     * The fraction in its lowest terms, `4/9`; null when its denominator is too long to read.
     *
     * The work is bounded: a term of more than twice `$digits` digits is not reduced at all and gives null; terms of
     * up to `NATIVE_DIGITS` digits are reduced on native integers; longer ones by ext-gmp, and give null without it.
     * `$digits` is taken between 1 and `MAX_FRACTION_DIGITS`.
     */
    #[AsTwigFilter('tilttek_dice_fraction')]
    public function fraction(Fraction $fraction, int $digits = self::FRACTION_DIGITS): ?string
    {
        $digits = max(1, min($digits, self::MAX_FRACTION_DIGITS));

        // A limb holds nine digits: past this many, a term is longer than twice the digits, without being written out.
        $limbs = intdiv(2 * $digits, 9) + 1;

        if ($fraction->numerator->size() > $limbs || $fraction->denominator->size() > $limbs) {
            return null; // too long, as the limbs say
        }

        $numerator = (string) $fraction->numerator;
        $denominator = (string) $fraction->denominator;

        if (\strlen($numerator) > 2 * $digits || \strlen($denominator) > 2 * $digits) {
            return null;
        }

        $reduced = $this->reduce($numerator, $denominator);

        if (null === $reduced || \strlen($reduced[1]) > $digits) {
            return null;
        }

        return ($fraction->negative ? '-' : '').$reduced[0].'/'.$reduced[1];
    }

    /**
     * The two terms divided by their greatest common divisor, on native integers when they fit, by ext-gmp otherwise;
     * null when they fit not and ext-gmp is not there.
     *
     * @param string $numerator   the digits of the numerator
     * @param string $denominator the digits of the denominator
     *
     * @return ?array{string, string}
     */
    private function reduce(string $numerator, string $denominator): ?array
    {
        if (\strlen($numerator) <= self::NATIVE_DIGITS && \strlen($denominator) <= self::NATIVE_DIGITS) {
            $top = (int) $numerator;
            $bottom = (int) $denominator;
            [$a, $b] = [$top, $bottom];

            while (0 !== $b) {
                [$a, $b] = [$b, $a % $b];
            }

            return [(string) intdiv($top, $a), (string) intdiv($bottom, $a)];
        }

        if (!$this->gmp) {
            return null;
        }

        $top = gmp_init($numerator);
        $bottom = gmp_init($denominator);
        $divisor = gmp_gcd($top, $bottom);

        return [gmp_strval(gmp_div_q($top, $divisor)), gmp_strval(gmp_div_q($bottom, $divisor))];
    }
}
