<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;

/**
 * The factions of one campaign, held by its game master: the scale their standings use, the factions in the order
 * they were given, and the relations between them. It is checked as a whole: each faction key once, each standing on
 * the scale, each relation between two factions of the roster and each pair once. Each change returns a new value.
 */
final readonly class Roster
{
    /** The most factions one roster holds. */
    public const int MAX_FACTIONS = 64;

    /** The most relations one roster holds. */
    public const int MAX_RELATIONS = 512;

    /** @var list<Faction> in the order they were given */
    public array $factions;

    /** @var list<Relation> in the order they were given */
    public array $relations;

    /**
     * @param string         $campaign   the identifier of the campaign
     * @param string         $gameMaster the identifier of the member who holds the roster
     * @param list<Faction>  $factions   up to `MAX_FACTIONS`, each key once
     * @param list<Relation> $relations  up to `MAX_RELATIONS`, each pair once, between factions of the roster
     * @param int            $version    0 for a roster not kept yet; the store raises it at each write
     *
     * @throws InvalidRoster when a value is out of its shape or its bounds, a key or a pair comes twice, a standing is
     *                       off the scale, or a relation names a faction the roster lacks
     */
    public function __construct(
        public string $campaign,
        public string $gameMaster,
        public Scale $scale,
        array $factions = [],
        array $relations = [],
        public int $version = 0,
    ) {
        Identifier::check($campaign, 'a campaign');
        Identifier::check($gameMaster, 'a member');

        if ($version < 0) {
            throw InvalidRoster::because('The version of a roster is 0 or more.');
        }

        if (\count($factions) > self::MAX_FACTIONS) {
            throw InvalidRoster::because(\sprintf('A roster holds at most %d factions.', self::MAX_FACTIONS));
        }

        if (\count($relations) > self::MAX_RELATIONS) {
            throw InvalidRoster::because(\sprintf('A roster holds at most %d relations.', self::MAX_RELATIONS));
        }

        $keys = array_map(static fn (Faction $faction): string => $faction->key, $factions);
        $twice = Identifier::twice($keys);

        if (null !== $twice) {
            throw InvalidRoster::because(\sprintf('The key %s comes twice among the factions.', $twice));
        }

        foreach ($factions as $faction) {
            foreach ($faction->standings as $standing) {
                if (!$scale->contains($standing->value)) {
                    throw InvalidRoster::because(\sprintf('The standing %d of %s with the faction %s is off the scale, from %d to %d.', $standing->value, $standing->subject->key, $faction->key, $scale->min, $scale->max));
                }
            }
        }

        $held = array_flip($keys);
        foreach ($relations as $relation) {
            if (!isset($held[$relation->first], $held[$relation->second])) {
                throw InvalidRoster::because(\sprintf('The relation between %s and %s names a faction the roster lacks.', $relation->first, $relation->second));
            }
        }

        $pairs = Identifier::twice(array_map(static fn (Relation $relation): string => $relation->key, $relations));

        if (null !== $pairs) {
            throw InvalidRoster::because(\sprintf('The factions %s are related twice.', str_replace(' ', ' and ', $pairs)));
        }

        $this->factions = $factions;
        $this->relations = $relations;
    }

    public function faction(string $key): ?Faction
    {
        foreach ($this->factions as $faction) {
            if ($faction->key === $key) {
                return $faction;
            }
        }

        return null;
    }

    /**
     * The faction of the key, or an exception.
     *
     * @throws InvalidRoster when no faction has the key
     */
    public function get(string $key): Faction
    {
        return $this->faction($key) ?? throw InvalidRoster::because(\sprintf('No faction of the roster has the key %s.', $key));
    }

    /**
     * Where the subject stands with the faction: the standing set, or the start of the scale when none was.
     *
     * @throws InvalidRoster when no faction has the key
     */
    public function standingOf(string $faction, Subject $subject): int
    {
        return $this->get($faction)->standing($subject)->value ?? $this->scale->start;
    }

    /**
     * The relation between two factions, in either order, or null.
     */
    public function relation(string $faction, string $other): ?Relation
    {
        $pair = Relation::pair($faction, $other);

        foreach ($this->relations as $relation) {
            if ($relation->key === $pair) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * The relations of a faction, in the order they were given.
     *
     * @return list<Relation>
     */
    public function relationsOf(string $faction): array
    {
        return array_values(array_filter($this->relations, static fn (Relation $relation): bool => $relation->involves($faction)));
    }

    /**
     * The roster with this faction in place of the faction of its key, or after the others when none has it.
     *
     * @throws InvalidRoster
     */
    public function withFaction(Faction $faction): self
    {
        $factions = $this->factions;

        foreach ($factions as $index => $kept) {
            if ($kept->key === $faction->key) {
                $factions[$index] = $faction;

                return $this->with(factions: $factions);
            }
        }

        $factions[] = $faction;

        return $this->with(factions: $factions);
    }

    /**
     * The roster without the faction of the key, nor its relations.
     *
     * @throws InvalidRoster when no faction has the key
     */
    public function withoutFaction(string $key): self
    {
        $this->get($key);

        return $this->with(
            factions: array_values(array_filter($this->factions, static fn (Faction $faction): bool => $faction->key !== $key)),
            relations: array_values(array_filter($this->relations, static fn (Relation $relation): bool => !$relation->involves($key))),
        );
    }

    /**
     * The roster with this relation in place of the relation of its pair, or after the others when none has it.
     *
     * @throws InvalidRoster
     */
    public function withRelation(Relation $relation): self
    {
        $relations = $this->relations;

        foreach ($relations as $index => $kept) {
            if ($kept->key === $relation->key) {
                $relations[$index] = $relation;

                return $this->with(relations: $relations);
            }
        }

        $relations[] = $relation;

        return $this->with(relations: $relations);
    }

    /**
     * The roster without the relation between two factions, in either order.
     *
     * @throws InvalidRoster when the two factions have no relation
     */
    public function withoutRelation(string $faction, string $other): self
    {
        if (!$this->relation($faction, $other) instanceof Relation) {
            throw InvalidRoster::because(\sprintf('The factions %s and %s have no relation.', $faction, $other));
        }

        $pair = Relation::pair($faction, $other);

        return $this->with(relations: array_values(array_filter($this->relations, static fn (Relation $relation): bool => $relation->key !== $pair)));
    }

    /**
     * @throws InvalidRoster when a standing is off the new scale
     */
    public function withScale(Scale $scale): self
    {
        return new self($this->campaign, $this->gameMaster, $scale, $this->factions, $this->relations, $this->version);
    }

    /**
     * @throws InvalidRoster
     */
    public function withGameMaster(string $gameMaster): self
    {
        return new self($this->campaign, $gameMaster, $this->scale, $this->factions, $this->relations, $this->version);
    }

    /**
     * @throws InvalidRoster when the version is below 0
     */
    public function withVersion(int $version): self
    {
        return new self($this->campaign, $this->gameMaster, $this->scale, $this->factions, $this->relations, $version);
    }

    /**
     * @param list<Faction>|null  $factions
     * @param list<Relation>|null $relations
     *
     * @throws InvalidRoster
     */
    private function with(?array $factions = null, ?array $relations = null): self
    {
        return new self($this->campaign, $this->gameMaster, $this->scale, $factions ?? $this->factions, $relations ?? $this->relations, $this->version);
    }
}
