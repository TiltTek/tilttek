<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * An option of a unit — a weapon, an upgrade, a mount —: its points each time it is taken, for the unit or for each of
 * its models, and how many times one unit may take it.
 */
final readonly class Option
{
    public string $code;

    public string $name;

    public int $points;

    public int $max;

    /**
     * @param int  $points   the points each time the option is taken, 0 to 100 000
     * @param int  $max      how many times one unit may take it, 1 to 1 000
     * @param bool $perModel whether the points count once for each model of the unit
     *
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(string $code, string $name, int $points = 0, int $max = 1, public bool $perModel = false)
    {
        $this->code = Identifiers::code($code, 'option');
        $this->name = Identifiers::name($name, 'option');
        $this->points = Identifiers::bounded($points, 0, Catalogue::MAX_POINTS, 'the points of the option "%s"', $code);
        $this->max = Identifiers::bounded($max, 1, Catalogue::MAX_COUNT, 'the most times the option "%s" is taken', $code);
    }

    /**
     * @throws InvalidCatalogue when the array does not read as an option
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'option', ['code', 'name'], ['points', 'max', 'per_model']);

        return new self(
            $input->string($object, 'code', 'option'),
            $input->string($object, 'name', 'option'),
            $input->int($object, 'points', 'option', 0),
            $input->int($object, 'max', 'option', 1),
            $input->bool($object, 'per_model', 'option'),
        );
    }

    /**
     * The points of the option taken that many times by a unit of that many models.
     */
    public function cost(int $count, int $models): int
    {
        return $this->points * $count * ($this->perModel ? $models : 1);
    }

    /**
     * @return array{code: string, name: string, points: int, max: int, per_model: bool}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'points' => $this->points, 'max' => $this->max, 'per_model' => $this->perModel];
    }
}
