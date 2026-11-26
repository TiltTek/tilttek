<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;

/**
 * What a condition does to one statistic of the character: an operation and its amount, multiplied by the condition's
 * value when `perValue` — frightened 2 takes 2 from every check.
 *
 * Additions of the same non-null `type` do not stack: of them, only the highest bonus and the lowest penalty count, as
 * typed bonuses do in many systems. Additions without a type always stack.
 */
final readonly class Modifier
{
    /** The largest amount of a modifier, either way; a multiplication goes from 0 to 1,000 percent. */
    public const int MAX_AMOUNT = 1_000_000;

    /** The highest percentage of a multiplication. */
    public const int MAX_PERCENT = 1_000;

    /**
     * @throws InvalidCondition when the statistic or the type is not an identifier, the amount is out of its bounds, or
     *                          a multiplication is by the value
     */
    public function __construct(
        public string $stat,
        public Operation $operation,
        public int $amount,
        public bool $perValue = false,
        public ?string $type = null,
    ) {
        Identifier::check($stat, 'a statistic');
        if (null !== $type) {
            Identifier::check($type, 'a modifier type');
        }

        if (Operation::Multiply === $operation) {
            if ($amount < 0 || $amount > self::MAX_PERCENT) {
                throw InvalidCondition::because(\sprintf('A multiplication is by 0 to %d percent.', self::MAX_PERCENT));
            }

            if ($perValue) {
                throw InvalidCondition::because('A multiplication is never by the value of the condition.');
            }
        } elseif (abs($amount) > self::MAX_AMOUNT) {
            throw InvalidCondition::because(\sprintf('The amount of a modifier is %d at most either way.', self::MAX_AMOUNT));
        }
    }

    public static function add(string $stat, int $amount, bool $perValue = false, ?string $type = null): self
    {
        return new self($stat, Operation::Add, $amount, $perValue, $type);
    }

    public static function multiply(string $stat, int $percent): self
    {
        return new self($stat, Operation::Multiply, $percent);
    }

    public static function upgrade(string $stat, int $floor): self
    {
        return new self($stat, Operation::Upgrade, $floor);
    }

    public static function downgrade(string $stat, int $cap): self
    {
        return new self($stat, Operation::Downgrade, $cap);
    }

    public static function override(string $stat, int $value): self
    {
        return new self($stat, Operation::Override, $value);
    }

    /**
     * The amount for a condition of a value: multiplied by it when `perValue`.
     */
    public function amountFor(?int $value): int
    {
        return $this->perValue ? $this->amount * ($value ?? 1) : $this->amount;
    }
}
