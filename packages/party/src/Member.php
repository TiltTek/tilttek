<?php

declare(strict_types=1);

namespace Tilttek\Party;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Party\Exception\InvalidParty;

/**
 * A member of a party — a character, under the reference the application gives it, `character:12` —, the roles it
 * fills in the party, by the keys the game system declares them under, its standing, and since when it belongs.
 *
 * A member fills 0 to 5 distinct roles, each a key; the library never checks them against a system: a role the system
 * does not declare is told by the composition, never refused.
 */
final readonly class Member
{
    public const int MAX_ROLES = 5;

    /** @var list<string> */
    public array $roles;

    /**
     * @param list<string> $roles the keys of the roles it fills, in the order the application gives them
     */
    public function __construct(
        public ResourceReference $party,
        public ResourceReference $member,
        array $roles,
        public Standing $standing,
        public DateTimeImmutable $since,
    ) {
        $distinct = [];

        foreach ($roles as $role) {
            $distinct[Text::key($role, 'a role')] = $role;
        }

        if (\count($distinct) > self::MAX_ROLES) {
            throw InvalidParty::because(\sprintf('A member fills at most %d roles; %d were given.', self::MAX_ROLES, \count($distinct)));
        }

        $this->roles = array_values($distinct);
    }

    /**
     * @param list<string> $roles
     */
    public function withRoles(array $roles): self
    {
        return new self($this->party, $this->member, $roles, $this->standing, $this->since);
    }

    public function withStanding(Standing $standing): self
    {
        return new self($this->party, $this->member, $this->roles, $standing, $this->since);
    }

    public function fills(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    public function isActive(): bool
    {
        return Standing::Active === $this->standing;
    }
}
