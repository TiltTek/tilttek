<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;

/**
 * A faction of a campaign, found by its key: its name, what it is, its ranks from the lowest to the highest, where the
 * party and each character stand with it, and the rank each member holds. A secret faction is shown to the game master
 * alone. It is checked as a whole; each change returns a new value.
 */
final readonly class Faction
{
    /** The longest name, in characters. */
    public const int MAX_NAME = 120;

    /** The longest summary, in characters. */
    public const int MAX_SUMMARY = 4_000;

    /** The most ranks one faction holds. */
    public const int MAX_RANKS = 16;

    /** The most subjects one faction holds a standing with. */
    public const int MAX_STANDINGS = 64;

    /** The most members one faction holds. */
    public const int MAX_MEMBERS = 64;

    public string $name;

    public string $summary;

    /** @var list<Rank> from the lowest to the highest */
    public array $ranks;

    /** @var list<Standing> in the order they were first set */
    public array $standings;

    /** @var list<Membership> in the order the characters joined */
    public array $members;

    /**
     * @param string           $key       the faction's key within its roster
     * @param string           $name      one line of 1 to `MAX_NAME` characters
     * @param string           $summary   lines of up to `MAX_SUMMARY` characters, possibly empty
     * @param list<Rank>       $ranks     up to `MAX_RANKS`, from the lowest to the highest, each key once
     * @param list<Standing>   $standings up to `MAX_STANDINGS`, each subject once
     * @param list<Membership> $members   up to `MAX_MEMBERS`, each character once, each holding a rank of the faction
     * @param bool             $secret    true when the players do not know of it
     *
     * @throws InvalidRoster when a value is out of its shape or its bounds, a key, a subject or a character comes twice,
     *                       or a member holds a rank the faction lacks
     */
    public function __construct(
        public string $key,
        string $name,
        string $summary = '',
        array $ranks = [],
        array $standings = [],
        array $members = [],
        public bool $secret = false,
    ) {
        Identifier::key($key, 'a faction');
        $this->name = Text::line($name, self::MAX_NAME, 'name of a faction');
        $this->summary = Text::block($summary, self::MAX_SUMMARY, 'summary of a faction');

        if (\count($ranks) > self::MAX_RANKS) {
            throw InvalidRoster::because(\sprintf('A faction holds at most %d ranks.', self::MAX_RANKS));
        }

        if (\count($standings) > self::MAX_STANDINGS) {
            throw InvalidRoster::because(\sprintf('A faction holds a standing with at most %d subjects.', self::MAX_STANDINGS));
        }

        if (\count($members) > self::MAX_MEMBERS) {
            throw InvalidRoster::because(\sprintf('A faction holds at most %d members.', self::MAX_MEMBERS));
        }

        $rankKeys = array_map(static fn (Rank $rank): string => $rank->key, $ranks);
        $this->once($rankKeys, 'The rank %s comes twice in the faction %s.', $key);
        $this->once(array_map(static fn (Standing $standing): string => $standing->subject->key, $standings), 'The subject %s has two standings with the faction %s.', $key);
        $this->once(array_map(static fn (Membership $member): string => $member->character, $members), 'The character %s is a member of the faction %s twice.', $key);

        $held = array_flip($rankKeys);
        foreach ($members as $member) {
            if (!isset($held[$member->rank])) {
                throw InvalidRoster::because(\sprintf('The character %s holds the rank %s, which the faction %s lacks.', $member->character, $member->rank, $key));
            }
        }

        $this->ranks = $ranks;
        $this->standings = $standings;
        $this->members = $members;
    }

    public function rank(string $key): ?Rank
    {
        foreach ($this->ranks as $rank) {
            if ($rank->key === $key) {
                return $rank;
            }
        }

        return null;
    }

    /**
     * Whether the first rank stands above the second, both by their key.
     *
     * @throws InvalidRoster when the faction lacks either rank
     */
    public function outranks(string $rank, string $than): bool
    {
        return $this->position($rank) > $this->position($than);
    }

    /**
     * The standing of the subject, or null when none was set.
     */
    public function standing(Subject $subject): ?Standing
    {
        foreach ($this->standings as $standing) {
            if ($standing->subject->equals($subject)) {
                return $standing;
            }
        }

        return null;
    }

    /**
     * The membership of the character, or null when they are not a member.
     */
    public function membership(string $character): ?Membership
    {
        foreach ($this->members as $member) {
            if ($member->character === $character) {
                return $member;
            }
        }

        return null;
    }

    /**
     * The faction with the subject at this standing, in place of the one they held, or after the others.
     *
     * @throws InvalidRoster when the faction holds as many standings as it may
     */
    public function withStanding(Subject $subject, int $value): self
    {
        $standings = $this->standings;
        $set = new Standing($subject, $value);

        foreach ($standings as $index => $standing) {
            if ($standing->subject->equals($subject)) {
                $standings[$index] = $set;

                return $this->with(standings: $standings);
            }
        }

        $standings[] = $set;

        return $this->with(standings: $standings);
    }

    /**
     * The faction with the character holding this rank, in place of the one they held, or after the other members; or
     * without them when the rank is null.
     *
     * @throws InvalidRoster when the faction lacks the rank, or holds as many members as it may
     */
    public function withMember(string $character, ?string $rank): self
    {
        if (null === $rank) {
            return $this->with(members: array_values(array_filter($this->members, static fn (Membership $member): bool => $member->character !== $character)));
        }

        $members = $this->members;
        $joined = new Membership($character, $rank);

        foreach ($members as $index => $member) {
            if ($member->character === $character) {
                $members[$index] = $joined;

                return $this->with(members: $members);
            }
        }

        $members[] = $joined;

        return $this->with(members: $members);
    }

    /**
     * The faction with the name, the summary, the ranks and the secrecy of the other one, and the standings and the
     * members of this one.
     *
     * @throws InvalidRoster when a member holds a rank the other one lacks
     */
    public function withDetailsOf(self $other): self
    {
        return new self($this->key, $other->name, $other->summary, $other->ranks, $this->standings, $this->members, $other->secret);
    }

    /**
     * Whether the other faction has the same name, summary, ranks and secrecy.
     */
    public function hasDetailsOf(self $other): bool
    {
        if ($this->name !== $other->name || $this->summary !== $other->summary || $this->secret !== $other->secret || \count($this->ranks) !== \count($other->ranks)) {
            return false;
        }

        return array_all($this->ranks, static fn (Rank $rank, int $index): bool => $rank->equals($other->ranks[$index]));
    }

    /**
     * @param list<Standing>|null   $standings
     * @param list<Membership>|null $members
     *
     * @throws InvalidRoster
     */
    private function with(?array $standings = null, ?array $members = null): self
    {
        return new self($this->key, $this->name, $this->summary, $this->ranks, $standings ?? $this->standings, $members ?? $this->members, $this->secret);
    }

    /**
     * @throws InvalidRoster
     */
    private function position(string $rank): int
    {
        foreach ($this->ranks as $index => $held) {
            if ($held->key === $rank) {
                return $index;
            }
        }

        throw InvalidRoster::because(\sprintf('The faction %s has no rank %s.', $this->key, $rank));
    }

    /**
     * @param list<string> $keys
     *
     * @throws InvalidRoster
     */
    private function once(array $keys, string $message, string $faction): void
    {
        $twice = Identifier::twice($keys);

        if (null !== $twice) {
            throw InvalidRoster::because(\sprintf($message, $twice, $faction));
        }
    }
}
