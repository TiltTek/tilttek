<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidArmyList;

/**
 * A detachment of an army list: the code of its type, an optional name the player gives it, and its units in order.
 */
final readonly class Detachment
{
    /**
     * The most units of a detachment.
     */
    public const int MAX_UNITS = 200;

    public string $type;

    public ?string $name;

    /** @var list<UnitSelection> */
    public array $units;

    /**
     * @param list<UnitSelection> $units
     *
     * @throws InvalidArmyList when a value is refused
     */
    public function __construct(string $type, ?string $name = null, array $units = [])
    {
        $this->type = Identifiers::code($type, 'detachment type', Fault::ArmyList);
        $this->name = null === $name ? null : Identifiers::name($name, 'detachment', Fault::ArmyList);

        if (\count($units) > self::MAX_UNITS) {
            throw InvalidArmyList::because(\sprintf('A detachment has at most %d units.', self::MAX_UNITS));
        }

        $this->units = $units;
    }

    /**
     * @throws InvalidArmyList when the array does not read as a detachment
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::ArmyList);
        $object = $input->object($data, 'detachment', ['type'], ['name', 'units']);

        return new self(
            $input->string($object, 'type', 'detachment'),
            $input->nullableString($object, 'name', 'detachment'),
            array_map(UnitSelection::fromArray(...), $input->list($object, 'units', 'detachment', self::MAX_UNITS)),
        );
    }

    /**
     * The detachment with one more unit, at the end.
     *
     * @throws InvalidArmyList when the detachment would have more than 200 units
     */
    public function withUnit(UnitSelection $unit): self
    {
        return new self($this->type, $this->name, [...$this->units, $unit]);
    }

    /**
     * The detachment with the unit at that position, from 0, replaced.
     *
     * @throws InvalidArmyList when no unit is at that position
     */
    public function withUnitAt(int $position, UnitSelection $unit): self
    {
        $units = $this->units;
        $units[$this->position($position, $units)] = $unit;

        return new self($this->type, $this->name, array_values($units));
    }

    /**
     * The detachment without the unit at that position, from 0.
     *
     * @throws InvalidArmyList when no unit is at that position
     */
    public function withoutUnit(int $position): self
    {
        $units = $this->units;
        unset($units[$this->position($position, $units)]);

        return new self($this->type, $this->name, array_values($units));
    }

    /**
     * @return array{type: string, name: ?string, units: list<array{unit: string, models: int, options: array<array-key, int>}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'units' => array_map(static fn (UnitSelection $unit): array => $unit->toArray(), $this->units),
        ];
    }

    /**
     * @param list<UnitSelection> $units
     *
     * @throws InvalidArmyList when no unit is at that position
     */
    private function position(int $position, array $units): int
    {
        if (!isset($units[$position])) {
            throw InvalidArmyList::because(\sprintf('The detachment has no unit at position %d.', $position));
        }

        return $position;
    }
}
