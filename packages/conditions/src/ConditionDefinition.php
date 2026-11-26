<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;

/**
 * A condition of the game system, declared by the site: poisoned, frightened, a disease, a mutation, a lost arm. It
 * says how a new application stacks with one in place, whether it has a value and up to what, how that value decays,
 * what it does to the statistics, the conditions it brings with it, and how long it lasts when the table says nothing.
 *
 * Its names and descriptions are the site's, in its own translations: the library knows the identifier alone.
 */
final readonly class ConditionDefinition
{
    /** The highest value of a condition. */
    public const int MAX_VALUE = 100;

    /** The most instances of a condition that stacks separately. */
    public const int MAX_INSTANCES = 16;

    /** The most modifiers of a condition. */
    public const int MAX_MODIFIERS = 32;

    /** The most conditions a condition brings with it. */
    public const int MAX_IMPLIES = 16;

    /** @var list<Modifier> */
    public array $modifiers;

    /** @var list<string> */
    public array $implies;

    /**
     * @param ?int           $maxValue     the highest value, or null for a condition without a value
     * @param list<Modifier> $modifiers    what it does to the statistics
     * @param list<string>   $implies      the conditions it brings with it while it lasts, each once, never itself
     * @param ?Duration      $duration     how long it lasts when the table gives no duration; until removed when null
     * @param int            $maxInstances the most instances, 2 or more when it stacks separately, otherwise 1
     *
     * @throws InvalidCondition when an identifier is not one, a bound is out of its range, or the rules contradict one
     *                          another: a sum or a decay or a modifier by the value without a value, separate instances
     *                          with a most of 1
     */
    public function __construct(
        public string $id,
        public Stacking $stacking = Stacking::Replace,
        public ?int $maxValue = null,
        array $modifiers = [],
        array $implies = [],
        public ?Duration $duration = null,
        public ?Decay $decay = null,
        public int $maxInstances = 1,
    ) {
        Identifier::check($id, 'a condition');
        if (null !== $maxValue && ($maxValue < 1 || $maxValue > self::MAX_VALUE)) {
            throw InvalidCondition::because(\sprintf('The highest value of a condition is 1 to %d.', self::MAX_VALUE));
        }

        if (\count($modifiers) > self::MAX_MODIFIERS) {
            throw InvalidCondition::because(\sprintf('A condition has %d modifiers at most.', self::MAX_MODIFIERS));
        }

        if (\count($implies) > self::MAX_IMPLIES) {
            throw InvalidCondition::because(\sprintf('A condition brings %d others at most.', self::MAX_IMPLIES));
        }

        $seen = [];
        foreach ($implies as $implied) {
            Identifier::check($implied, 'an implied condition');
            if ($implied === $id || isset($seen[$implied])) {
                throw InvalidCondition::because(\sprintf('The condition %s brings %s twice or brings itself.', $id, $implied));
            }

            $seen[$implied] = true;
        }

        if (null === $maxValue) {
            if (Stacking::Sum === $stacking || $decay instanceof Decay) {
                throw InvalidCondition::because(\sprintf('The condition %s sums or decays, and has no value.', $id));
            }

            foreach ($modifiers as $modifier) {
                if ($modifier->perValue) {
                    throw InvalidCondition::because(\sprintf('The condition %s modifies by its value, and has none.', $id));
                }
            }
        }

        $separate = Stacking::Separate === $stacking;
        if ($separate ? $maxInstances < 2 || $maxInstances > self::MAX_INSTANCES : 1 !== $maxInstances) {
            throw InvalidCondition::because(\sprintf('A condition that stacks separately has 2 to %d instances; any other has 1.', self::MAX_INSTANCES));
        }

        $this->modifiers = $modifiers;
        $this->implies = $implies;
    }

    public function hasValue(): bool
    {
        return null !== $this->maxValue;
    }
}
