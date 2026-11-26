<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use DateTimeImmutable;
use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;

/**
 * One application of a condition on a character: its instance identifier, the condition, its value, its source — the
 * character or the object that caused it, by identifier — what is left of its duration, the moment it ends in real
 * time, and when it was applied.
 */
final readonly class ActiveCondition
{
    /**
     * @param ?int               $value     1 to the highest value of a condition, or null for one without a value
     * @param ?DateTimeImmutable $expiresAt the moment it ends, for a duration in real time only
     *
     * @throws InvalidCondition when an identifier is not one, the value is out of its bounds, or the moment of the end
     *                          is given for a duration not in real time or missing for one
     */
    public function __construct(
        public string $id,
        public string $condition,
        public ?int $value,
        public ?string $source,
        public Duration $duration,
        public DateTimeImmutable $appliedAt,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        Identifier::check($id, 'a condition instance');
        Identifier::check($condition, 'a condition');
        if (null !== $source) {
            Identifier::check($source, 'a source');
        }

        if (null !== $value && ($value < 1 || $value > ConditionDefinition::MAX_VALUE)) {
            throw InvalidCondition::because(\sprintf('The value of a condition is 1 to %d.', ConditionDefinition::MAX_VALUE));
        }

        if ((DurationKind::RealTime === $duration->kind) !== $expiresAt instanceof DateTimeImmutable) {
            throw InvalidCondition::because('A condition has the moment of its end when, and only when, it lasts in real time.');
        }
    }

    /**
     * The condition with another value and the rest unchanged.
     */
    public function withValue(int $value): self
    {
        return new self($this->id, $this->condition, $value, $this->source, $this->duration, $this->appliedAt, $this->expiresAt);
    }

    /**
     * The condition with what is left of its duration.
     */
    public function withDuration(Duration $duration): self
    {
        return new self($this->id, $this->condition, $this->value, $this->source, $duration, $this->appliedAt, $this->expiresAt);
    }
}
