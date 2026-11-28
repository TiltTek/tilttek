<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

use Tilttek\Party\Exception\InvalidParty;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\PartyRole;

/**
 * The roles a game system or the application declares for a party, 0 to 50 of them with distinct keys, in the order
 * they are listed. Roles are optional: a game without classes or jobs has none, and an empty catalogue gives no advice.
 *
 * ```php
 * $roles = RoleCatalogue::fromSystem($system);          // the party roles the system declares, maybe none
 * $roles = new RoleCatalogue([new Role('healer', 'Healer'), new Role('scout', 'Scout', minimum: 0)]);
 * $roles = RoleCatalogue::none();                       // a game without roles
 * ```
 */
final readonly class RoleCatalogue
{
    public const int MAX_ROLES = 50;

    /** @var array<string, Role> */
    private array $roles;

    /**
     * @param list<Role> $roles
     *
     * @throws InvalidParty
     */
    public function __construct(array $roles)
    {
        $byKey = [];

        foreach ($roles as $role) {
            if (isset($byKey[$role->key])) {
                throw InvalidParty::because(\sprintf('Two roles share the key "%s".', $role->key));
            }

            $byKey[$role->key] = $role;
        }

        if (\count($byKey) > self::MAX_ROLES) {
            throw InvalidParty::because(\sprintf('A role catalogue holds at most %d roles; it holds %d.', self::MAX_ROLES, \count($byKey)));
        }

        $this->roles = $byKey;
    }

    /**
     * A catalogue without roles, for a game without classes or jobs: its composition gives no advice.
     */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * The party roles a system declares, in its order, each named in a locale and with the same minimum; a system that
     * declares none gives an empty catalogue.
     *
     * @param string $locale  the locale of the names, the system's English one when it has no text in it
     * @param int    $minimum the fewest members of each role in a balanced party
     *
     * @throws InvalidParty when a role is out of bounds
     */
    public static function fromSystem(GameSystem $system, string $locale = 'en', int $minimum = 1): self
    {
        return new self(array_map(static fn (PartyRole $role): Role => new Role($role->key, (string) $role->names->in($locale), $minimum), $system->partyRoles()));
    }

    /**
     * True when the catalogue declares no role: the game has none, and a composition read against it gives no advice.
     */
    public function isEmpty(): bool
    {
        return [] === $this->roles;
    }

    public function has(string $key): bool
    {
        return isset($this->roles[$key]);
    }

    public function role(string $key): ?Role
    {
        return $this->roles[$key] ?? null;
    }

    /**
     * @return list<Role> in the order of the catalogue
     */
    public function roles(): array
    {
        return array_values($this->roles);
    }
}
