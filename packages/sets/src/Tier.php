<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Tilttek\Sets\Exception\InvalidSet;

/**
 * A step of a set beyond or before its completion: the number of members a holder owns at a level or higher — *5 of
 * 20*, *all 20 at level 2* —, with the rewards that step declares. A set redone at higher levels declares one tier per
 * level; `required` left out means every member of the set.
 */
final readonly class Tier
{
    public const int MAX_LEVEL = 1000;

    public const int MAX_REWARDS = 20;

    /**
     * @param list<Reward> $rewards
     *
     * @throws InvalidSet when the code, the level, the number required or the rewards are refused
     */
    public function __construct(
        public string $code,
        public int $level = 1,
        public ?int $required = null,
        public array $rewards = [],
    ) {
        Identifiers::code($code, 'tier');

        if ($level < 1 || $level > self::MAX_LEVEL) {
            throw InvalidSet::because(\sprintf('The level of the tier "%s" is 1 to %d; %d is not.', $code, self::MAX_LEVEL, $level));
        }

        if (null !== $required && $required < 1) {
            throw InvalidSet::because(\sprintf('The tier "%s" requires at least one member.', $code));
        }

        if (\count($rewards) > self::MAX_REWARDS) {
            throw InvalidSet::because(\sprintf('The tier "%s" declares at most %d rewards.', $code, self::MAX_REWARDS));
        }
    }

    /**
     * How many members the tier requires in a set of `$members`.
     */
    public function requiredOf(int $members): int
    {
        return $this->required ?? $members;
    }

    /**
     * @throws InvalidSet when the value is not a tier {@see self::toArray()} wrote
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'tier', ['code'], ['level', 'required', 'rewards']);
        $required = $object['required'] ?? null;

        if (null !== $required && !\is_int($required)) {
            throw InvalidSet::because('The "required" of a tier is an integer.');
        }

        return new self(
            ArrayInput::string($object, 'code', 'tier'),
            ArrayInput::int($object, 'level', 'tier', 1),
            $required,
            array_map(Reward::fromArray(...), ArrayInput::list($object, 'rewards', 'tier', self::MAX_REWARDS)),
        );
    }

    /**
     * @return array{code: string, level: int, required: ?int, rewards: list<array{kind: string, reference: string, quantity: int}>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'level' => $this->level,
            'required' => $this->required,
            'rewards' => array_map(static fn (Reward $reward): array => $reward->toArray(), $this->rewards),
        ];
    }
}
