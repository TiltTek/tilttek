<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The modifiers of a set of dice, applied in this order whatever the order they were written in. While each die is
 * rolled: the reroll, the reroll of a value already shown (unique), the explosion; once every die is rolled, the
 * explosion of dice all the same. Then, on the dice rolled: the floor and the ceiling of each die, its
 * transformations, the filter, the selection, the count of successes and of failures — or the sets of occurrences, or
 * the groups that reach a sum —, the critical marks, the look tokens, and the order the dice are listed in.
 *
 * Each modifier is given once. Failures need successes judged die by die; a set counts successes, occurrences or
 * groups, one of them at most; a floor stays at or below its ceiling; only the last look token may go without a count.
 */
final readonly class Modifiers
{
    /**
     * @param list<Transform> $transforms the first that matches a die applies
     * @param list<Look>      $looks
     */
    public function __construct(
        public ?Reroll $reroll = null,
        public ?Repetition $unique = null,
        public ?Explosion $explosion = null,
        public bool $explodeAllSame = false,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public array $transforms = [],
        public ?Condition $filter = null,
        public ?Selection $selection = null,
        public ?Condition $success = null,
        public ?Comparison $failure = null,
        public ?Occurrences $occurrences = null,
        public ?int $groups = null,
        public ?Critical $criticalSuccess = null,
        public ?Critical $criticalFailure = null,
        public array $looks = [],
        public ?Sort $sort = null,
    ) {
        if ($failure instanceof Comparison && (!$success instanceof Condition || !$success->scope->eachDie())) {
            throw InvalidDefinition::because('Failures take a success away: they need successes judged die by die.');
        }

        if ($failure instanceof Comparison && !$failure->isPlain()) {
            throw InvalidDefinition::because('Failures are judged by a plain comparison, not by "!=".');
        }

        if (\count(array_filter([$success, $occurrences, $groups], static fn (mixed $count): bool => null !== $count)) > 1) {
            throw InvalidDefinition::because('A set of dice counts successes, occurrences or groups: one of them at most.');
        }

        if (null !== $minimum && null !== $maximum && $minimum > $maximum) {
            throw InvalidDefinition::because(\sprintf('The floor of a die, %d, is above its ceiling, %d.', $minimum, $maximum));
        }

        if (null !== $groups && $groups < 1) {
            throw InvalidDefinition::because(\sprintf('Groups reach a sum of at least 1; %d is not.', $groups));
        }

        foreach (\array_slice($looks, 0, -1) as $look) {
            if (null === $look->count) {
                throw InvalidDefinition::because('Only the last look token goes to every die left; the others give their count.');
            }
        }
    }

    /**
     * Whether any modifier is given.
     */
    public function isEmpty(): bool
    {
        return !$this->rolls() && [] === $this->transforms && [] === $this->looks
            && [] === array_filter([$this->minimum, $this->maximum, $this->filter, $this->selection, $this->success, $this->failure, $this->occurrences, $this->groups, $this->criticalSuccess, $this->criticalFailure, $this->sort], static fn (mixed $modifier): bool => null !== $modifier);
    }

    /**
     * Whether a modifier rolls more dice: a reroll, a unique reroll, an explosion. A list of values and the dice of a
     * set before take none.
     */
    public function rolls(): bool
    {
        return $this->reroll instanceof Reroll || $this->unique instanceof Repetition || $this->explosion instanceof Explosion || $this->explodeAllSame;
    }

    /**
     * Checks the modifiers against the dice they apply to: how many, and their lowest and highest faces.
     *
     * @throws InvalidDefinition when a selection keeps more dice than rolled or drops them all, or a roll would never
     *                           end
     */
    public function check(int $count, int $lowest, int $highest): void
    {
        if ($this->selection instanceof Selection && $this->selection->count > ($this->selection->mode->keeps() ? $count : $count - 1)) {
            throw InvalidDefinition::because(\sprintf('%d dice cannot keep %d, nor drop all of them.', $count, $this->selection->count));
        }

        if ($this->explosion instanceof Explosion && !$this->explosion->limit instanceof Node && $this->explosion->always($lowest, $highest)) {
            throw InvalidDefinition::because('Every face would explode: the roll would never end.');
        }

        if (Repetition::Repeated === $this->reroll?->repetition && $this->reroll->on->matchesAll($lowest, $highest)) {
            throw InvalidDefinition::because('Every face would be rerolled: the roll would never end.');
        }

        if (Repetition::Repeated === $this->unique && $count > $highest - $lowest + 1) {
            throw InvalidDefinition::because(\sprintf('%d dice cannot all differ with %d faces.', $count, $highest - $lowest + 1));
        }

        if ($this->explodeAllSame && $count < 2) {
            throw InvalidDefinition::because('Dice all the same explode from two dice: one die is always the same as itself.');
        }
    }
}
