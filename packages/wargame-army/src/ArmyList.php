<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidArmyList;

/**
 * An army list: its identifier and name, the catalogue and the version of it the list was built with, the points
 * limit chosen for the game, and its detachments. A list never changes: each change gives a new list.
 */
final readonly class ArmyList
{
    /**
     * The most detachments of a list.
     */
    public const int MAX_DETACHMENTS = 20;

    /**
     * The highest points limit.
     */
    public const int MAX_POINTS_LIMIT = 1_000_000;

    public string $id;

    public string $name;

    public string $catalogue;

    public string $catalogueVersion;

    public int $pointsLimit;

    /** @var list<Detachment> */
    public array $detachments;

    /**
     * @param list<Detachment> $detachments
     *
     * @throws InvalidArmyList when a value is refused
     */
    public function __construct(string $id, string $name, string $catalogue, string $catalogueVersion, int $pointsLimit, array $detachments = [])
    {
        $this->id = Identifiers::id($id);
        $this->name = Identifiers::name($name, 'army list', Fault::ArmyList);
        $this->catalogue = Identifiers::code($catalogue, 'catalogue', Fault::ArmyList);
        $this->catalogueVersion = Identifiers::version($catalogueVersion, Fault::ArmyList);
        $this->pointsLimit = Identifiers::bounded($pointsLimit, 1, self::MAX_POINTS_LIMIT, 'the points limit of an army list', fault: Fault::ArmyList);

        if (\count($detachments) > self::MAX_DETACHMENTS) {
            throw InvalidArmyList::because(\sprintf('An army list has at most %d detachments.', self::MAX_DETACHMENTS));
        }

        $this->detachments = $detachments;
    }

    /**
     * An empty list built with a catalogue, at its current version.
     *
     * @throws InvalidArmyList when a value is refused
     */
    public static function for(Catalogue $catalogue, string $id, string $name, int $pointsLimit): self
    {
        return new self($id, $name, $catalogue->code, $catalogue->version, $pointsLimit);
    }

    /**
     * @throws InvalidArmyList when the array does not read as an army list
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::ArmyList);
        $object = $input->object($data, 'army list', ['id', 'name', 'catalogue', 'catalogue_version', 'points_limit'], ['detachments']);

        return new self(
            $input->string($object, 'id', 'army list'),
            $input->string($object, 'name', 'army list'),
            $input->string($object, 'catalogue', 'army list'),
            $input->string($object, 'catalogue_version', 'army list'),
            $input->int($object, 'points_limit', 'army list'),
            array_map(Detachment::fromArray(...), $input->list($object, 'detachments', 'army list', self::MAX_DETACHMENTS)),
        );
    }

    /**
     * @throws InvalidArmyList when the text is not JSON or does not read as an army list
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(new ArrayInput(Fault::ArmyList)->json($json, 'army list'));
    }

    /**
     * @throws InvalidArmyList when the list would have more than 20 detachments
     */
    public function withDetachment(Detachment $detachment): self
    {
        return $this->with([...$this->detachments, $detachment]);
    }

    /**
     * The list with the detachment at that position, from 0, replaced.
     *
     * @throws InvalidArmyList when no detachment is at that position
     */
    public function withDetachmentAt(int $position, Detachment $detachment): self
    {
        $detachments = $this->detachments;
        $detachments[$this->position($position)] = $detachment;

        return $this->with(array_values($detachments));
    }

    /**
     * The list without the detachment at that position, from 0.
     *
     * @throws InvalidArmyList when no detachment is at that position
     */
    public function withoutDetachment(int $position): self
    {
        $detachments = $this->detachments;
        unset($detachments[$this->position($position)]);

        return $this->with(array_values($detachments));
    }

    /**
     * @throws InvalidArmyList when the name is refused
     */
    public function renamed(string $name): self
    {
        return new self($this->id, $name, $this->catalogue, $this->catalogueVersion, $this->pointsLimit, $this->detachments);
    }

    /**
     * @throws InvalidArmyList when the limit is refused
     */
    public function withPointsLimit(int $pointsLimit): self
    {
        return new self($this->id, $this->name, $this->catalogue, $this->catalogueVersion, $pointsLimit, $this->detachments);
    }

    /**
     * The list moved to another version of its catalogue, for the validator to check it against.
     *
     * @throws InvalidArmyList when the version is refused
     */
    public function withCatalogueVersion(string $version): self
    {
        return new self($this->id, $this->name, $this->catalogue, $version, $this->pointsLimit, $this->detachments);
    }

    /**
     * @return array{id: string, name: string, catalogue: string, catalogue_version: string, points_limit: int, detachments: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'catalogue' => $this->catalogue,
            'catalogue_version' => $this->catalogueVersion,
            'points_limit' => $this->pointsLimit,
            'detachments' => array_map(static fn (Detachment $detachment): array => $detachment->toArray(), $this->detachments),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @param list<Detachment> $detachments
     */
    private function with(array $detachments): self
    {
        return new self($this->id, $this->name, $this->catalogue, $this->catalogueVersion, $this->pointsLimit, $detachments);
    }

    /**
     * @throws InvalidArmyList when no detachment is at that position
     */
    private function position(int $position): int
    {
        if (!isset($this->detachments[$position])) {
            throw InvalidArmyList::because(\sprintf('The army list has no detachment at position %d.', $position));
        }

        return $position;
    }
}
