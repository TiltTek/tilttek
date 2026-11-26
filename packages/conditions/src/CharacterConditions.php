<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;

/**
 * The conditions of one character: the applications in the order they were made, the number of the last instance
 * identifier given, the last tick counted — so that a turn or a time advance told twice counts once — and its version in
 * the store.
 */
final readonly class CharacterConditions
{
    /** The most conditions on a character at once. */
    public const int MAX_ACTIVE = 64;

    /** @var list<ActiveCondition> */
    public array $active;

    /**
     * @param list<ActiveCondition> $active   in the order applied, each instance identifier once
     * @param int                   $issued   the number of the last instance identifier given
     * @param ?string               $lastTick the identifier of the last turn boundary or time advance counted
     *
     * @throws InvalidCondition when an identifier is not one, an instance comes twice, there are too many, or a count is
     *                          below 0
     */
    public function __construct(
        public string $character,
        array $active = [],
        public int $issued = 0,
        public ?string $lastTick = null,
        public int $version = 0,
    ) {
        Identifier::check($character, 'a character');
        if (null !== $lastTick) {
            Identifier::check($lastTick, 'a tick');
        }

        if (\count($active) > self::MAX_ACTIVE) {
            throw InvalidCondition::because(\sprintf('A character has %d conditions at most.', self::MAX_ACTIVE));
        }

        $seen = [];
        foreach ($active as $condition) {
            if (isset($seen[$condition->id])) {
                throw InvalidCondition::because(\sprintf('The condition instance %s comes twice.', $condition->id));
            }

            $seen[$condition->id] = true;
        }

        if ($issued < 0 || $version < 0) {
            throw InvalidCondition::because('The number of instances and the version are 0 or more.');
        }

        $this->active = $active;
    }

    public function instance(string $id): ?ActiveCondition
    {
        foreach ($this->active as $condition) {
            if ($condition->id === $id) {
                return $condition;
            }
        }

        return null;
    }

    /**
     * @return list<ActiveCondition> the applications of a condition, in the order applied
     */
    public function instancesOf(string $condition): array
    {
        return array_values(array_filter($this->active, static fn (ActiveCondition $one): bool => $one->condition === $condition));
    }

    /**
     * @return list<string> the conditions applied, each once, in the order first applied
     */
    public function conditionIds(): array
    {
        return array_values(array_unique(array_map(static fn (ActiveCondition $one): string => $one->condition, $this->active)));
    }

    /**
     * @param list<ActiveCondition> $active
     */
    public function with(array $active, int $issued, ?string $lastTick): self
    {
        return new self($this->character, $active, $issued, $lastTick, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->character, $this->active, $this->issued, $this->lastTick, $version);
    }
}
