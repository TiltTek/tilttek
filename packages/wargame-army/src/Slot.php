<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * What a detachment takes of a category: the fewest and the most units of it.
 */
final readonly class Slot
{
    public string $category;

    public int $min;

    /**
     * The most units; null for no limit.
     */
    public ?int $max;

    /**
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(string $category, int $min = 0, ?int $max = null)
    {
        $this->category = Identifiers::code($category, 'category');
        $this->min = Identifiers::bounded($min, 0, Detachment::MAX_UNITS, 'the fewest units of the slot "%s"', $category);
        $this->max = null === $max ? null : Identifiers::bounded($max, max(1, $min), Detachment::MAX_UNITS, 'the most units of the slot "%s"', $category);
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a slot
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'slot', ['category'], ['min', 'max']);

        return new self($input->string($object, 'category', 'slot'), $input->int($object, 'min', 'slot', 0), $input->nullableInt($object, 'max', 'slot'));
    }

    /**
     * @return array{category: string, min: int, max: ?int}
     */
    public function toArray(): array
    {
        return ['category' => $this->category, 'min' => $this->min, 'max' => $this->max];
    }
}
