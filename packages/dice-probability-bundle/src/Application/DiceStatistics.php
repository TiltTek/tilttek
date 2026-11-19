<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\DiceProbability\Chance;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Exception\CannotCompute;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Versus;
use Tilttek\DiceProbabilityBundle\Application\Exception\GroupsTooCostly;
use Tilttek\DiceProbabilityBundle\Application\Exception\TooManyOutcomes;

/**
 * The probabilities of expressions written as text, read with the application's notations and custom dice: the
 * distribution of one, two against each other, and an event on a set of dice. An expression whose groups would take
 * too long to search, as `GroupSearch` weighs them, is refused before the library rolls it; a distribution of more
 * values than `maxOutcomes` is refused: its table and its chart would be too long to draw.
 */
final readonly class DiceStatistics
{
    /** The most values of a distribution, by default. */
    public const int MAX_OUTCOMES = 500;

    public function __construct(
        private DiceRoller $dice,
        private Probabilities $probabilities,
        private Rounding $rounding = Rounding::Floor,
        private int $maxOutcomes = self::MAX_OUTCOMES,
        private GroupSearch $groups = new GroupSearch(),
    ) {
    }

    /**
     * @param ?string   $locale   the language whose notation the expression is written in; the canonical one when null
     * @param ?Rounding $rounding how a division rounds; the configured rounding when null
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws GroupsTooCostly
     * @throws TooManyOutcomes
     */
    public function distribution(string $expression, ?string $locale = null, ?Rounding $rounding = null): Distribution
    {
        $pool = $this->dice->parse($expression, $locale);
        $this->groups->check($pool, $expression);
        $distribution = $this->probabilities->distribution($pool, ($rounding ?? $this->rounding)->mode());

        if (\count($distribution->weights) > $this->maxOutcomes) {
            throw TooManyOutcomes::of($expression, \count($distribution->weights), $this->maxOutcomes);
        }

        return $distribution;
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws GroupsTooCostly
     * @throws TooManyOutcomes
     */
    public function compare(string $first, string $second, ?string $locale = null, ?Rounding $rounding = null): Versus
    {
        return new Versus($this->distribution($first, $locale, $rounding), $this->distribution($second, $locale, $rounding));
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotCompute
     * @throws GroupsTooCostly
     */
    public function chance(Event $event, string $dice, ?string $locale = null): Chance
    {
        $pool = $this->dice->parse($dice, $locale);
        $this->groups->check($pool, $dice);

        return $this->probabilities->chance($event, $pool);
    }

    /**
     * The canonical form of an expression, or the expression as written when it cannot be read: what a caption shows.
     */
    public function canonical(string $expression, ?string $locale = null): string
    {
        try {
            return $this->dice->parse($expression, $locale)->canonical();
        } catch (InvalidExpression|LimitExceeded) {
            return $expression;
        }
    }
}
