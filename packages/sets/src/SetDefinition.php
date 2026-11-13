<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use JsonException;
use Tilttek\Sets\Exception\InvalidSet;

/**
 * A version of a set: its code, its members by stable identifier in the order they are shown, its tiers from the
 * easiest to the hardest, and the rewards its completion declares. A changed set takes a new version; a completion
 * recorded under an earlier version stays.
 */
final readonly class SetDefinition
{
    public const int MAX_MEMBERS = 1000;

    public const int MAX_TIERS = 20;

    public const int MAX_REWARDS = 20;

    public const int MAX_VERSION = 1_000_000;

    public const int MAX_JSON_LENGTH = 1_048_576;

    /**
     * @param list<string> $members
     * @param list<Tier>   $tiers
     * @param list<Reward> $rewards
     *
     * @throws InvalidSet when the code, the version, a member, the order of the tiers or the rewards are refused
     */
    public function __construct(
        public string $code,
        public int $version,
        public array $members,
        public array $tiers = [],
        public array $rewards = [],
    ) {
        Identifiers::code($code, 'set');

        if ($version < 1 || $version > self::MAX_VERSION) {
            throw InvalidSet::because(\sprintf('The version of the set "%s" is 1 to %d; %d is not.', $code, self::MAX_VERSION, $version));
        }

        if ([] === $members) {
            throw InvalidSet::because(\sprintf('The set "%s" has at least one member.', $code));
        }

        if (\count($members) > self::MAX_MEMBERS) {
            throw InvalidSet::because(\sprintf('The set "%s" has at most %d members.', $code, self::MAX_MEMBERS));
        }

        $seen = [];

        foreach ($members as $member) {
            if (isset($seen[Identifiers::member($member)])) {
                throw InvalidSet::because(\sprintf('The set "%s" names the member "%s" twice.', $code, $member));
            }

            $seen[$member] = $member;
        }

        if (\count($tiers) > self::MAX_TIERS) {
            throw InvalidSet::because(\sprintf('The set "%s" has at most %d tiers.', $code, self::MAX_TIERS));
        }

        if (\count($rewards) > self::MAX_REWARDS) {
            throw InvalidSet::because(\sprintf('The set "%s" declares at most %d rewards.', $code, self::MAX_REWARDS));
        }

        $this->checkTiers();
    }

    /**
     * The members owned and missing, the percentage and the tiers reached by the holdings given.
     */
    public function evaluate(Holdings $holdings): Evaluation
    {
        $owned = [];
        $missing = [];

        foreach ($this->members as $member) {
            if (0 < $holdings->levelOf($member)) {
                $owned[] = $member;
            } else {
                $missing[] = $member;
            }
        }

        $reached = [];

        foreach ($this->tiers as $tier) {
            $count = 0;

            foreach ($owned as $member) {
                if ($holdings->levelOf($member) >= $tier->level) {
                    ++$count;
                }
            }

            if ($count >= $tier->requiredOf(\count($this->members))) {
                $reached[] = $tier;
            }
        }

        return new Evaluation($this, $owned, $missing, $reached);
    }

    public function tier(string $code): ?Tier
    {
        foreach ($this->tiers as $tier) {
            if ($tier->code === $code) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Whether two definitions are the same version with the same content.
     */
    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @throws InvalidSet when the value is not a definition {@see self::toArray()} wrote
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'set', ['code', 'version', 'members'], ['tiers', 'rewards']);
        $members = [];

        foreach (ArrayInput::list($object, 'members', 'set', self::MAX_MEMBERS) as $member) {
            if (!\is_string($member)) {
                throw InvalidSet::because('A member of a set is a string.');
            }

            $members[] = $member;
        }

        return new self(
            ArrayInput::string($object, 'code', 'set'),
            ArrayInput::int($object, 'version', 'set'),
            $members,
            array_map(Tier::fromArray(...), ArrayInput::list($object, 'tiers', 'set', self::MAX_TIERS)),
            array_map(Reward::fromArray(...), ArrayInput::list($object, 'rewards', 'set', self::MAX_REWARDS)),
        );
    }

    /**
     * @throws InvalidSet when the text is not the JSON of a definition, or longer than {@see self::MAX_JSON_LENGTH} or nested deeper than a definition
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_LENGTH) {
            throw InvalidSet::because(\sprintf('The JSON of a set is at most %d bytes.', self::MAX_JSON_LENGTH));
        }

        try {
            $data = json_decode($json, true, 6, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidSet::because('The JSON of a set does not read: '.$jsonException->getMessage());
        }

        return self::fromArray($data);
    }

    /**
     * @return array{code: string, version: int, members: list<string>, tiers: list<array{code: string, level: int, required: ?int, rewards: list<array{kind: string, reference: string, quantity: int}>}>, rewards: list<array{kind: string, reference: string, quantity: int}>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'members' => $this->members,
            'tiers' => array_map(static fn (Tier $tier): array => $tier->toArray(), $this->tiers),
            'rewards' => array_map(static fn (Reward $reward): array => $reward->toArray(), $this->rewards),
        ];
    }

    /**
     * @throws JsonException never: every value of a definition encodes
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Each tier is reached only by a holder who reached the one before: its level and its number required are never
     * lower than those of the tier before, and at least one of them is higher.
     */
    private function checkTiers(): void
    {
        $members = \count($this->members);
        $codes = [];
        $before = null;

        foreach ($this->tiers as $tier) {
            if (isset($codes[$tier->code])) {
                throw InvalidSet::because(\sprintf('The set "%s" names the tier "%s" twice.', $this->code, $tier->code));
            }

            $codes[$tier->code] = $tier->code;
            $required = $tier->requiredOf($members);

            if ($required > $members) {
                throw InvalidSet::because(\sprintf('The tier "%s" requires %d members; the set "%s" has %d.', $tier->code, $required, $this->code, $members));
            }

            if (null !== $before) {
                $previous = $before->requiredOf($members);

                if ($tier->level < $before->level || $required < $previous || ($tier->level === $before->level && $required === $previous)) {
                    throw InvalidSet::because(\sprintf('The tier "%s" of the set "%s" must be harder than the tier "%s" before it: a level and a number required never lower, and one of them higher.', $tier->code, $this->code, $before->code));
                }
            }

            $before = $tier;
        }
    }
}
