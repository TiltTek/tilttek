<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * A kind of detachment a catalogue offers: the slots it fills by category and the most of it an army takes. A unit
 * fills the slot of its first category the detachment takes; a unit of no such category is not allowed in it.
 */
final readonly class DetachmentType
{
    /**
     * The most slots of a detachment type.
     */
    public const int MAX_SLOTS = 50;

    public string $code;

    public string $name;

    /** @var list<Slot> */
    public array $slots;

    /**
     * The most detachments of this type in an army; null for no limit.
     */
    public ?int $max;

    /**
     * @param list<Slot> $slots 1 to 50 slots, each category once
     *
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(string $code, string $name, array $slots, ?int $max = null)
    {
        $this->code = Identifiers::code($code, 'detachment type');
        $this->name = Identifiers::name($name, 'detachment type');
        $this->max = null === $max ? null : Identifiers::bounded($max, 1, ArmyList::MAX_DETACHMENTS, 'the most detachments "%s"', $code);

        if ([] === $slots || \count($slots) > self::MAX_SLOTS) {
            throw InvalidCatalogue::because(\sprintf('The detachment type "%s" has 1 to %d slots.', $code, self::MAX_SLOTS));
        }

        $categories = [];

        foreach ($slots as $slot) {
            if (isset($categories[$slot->category])) {
                throw InvalidCatalogue::because(\sprintf('The detachment type "%s" has two slots "%s".', $code, $slot->category));
            }

            $categories[$slot->category] = true;
        }

        $this->slots = $slots;
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a detachment type
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'detachment type', ['code', 'name', 'slots'], ['max']);

        return new self(
            $input->string($object, 'code', 'detachment type'),
            $input->string($object, 'name', 'detachment type'),
            array_map(Slot::fromArray(...), $input->list($object, 'slots', 'detachment type', self::MAX_SLOTS)),
            $input->nullableInt($object, 'max', 'detachment type'),
        );
    }

    /**
     * The slot a unit fills: the one of its first category the detachment takes, or null.
     */
    public function slotFor(UnitEntry $unit): ?Slot
    {
        foreach ($unit->categories as $category) {
            foreach ($this->slots as $slot) {
                if ($category === $slot->category) {
                    return $slot;
                }
            }
        }

        return null;
    }

    /**
     * @return array{code: string, name: string, slots: list<array{category: string, min: int, max: ?int}>, max: ?int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'slots' => array_map(static fn (Slot $slot): array => $slot->toArray(), $this->slots),
            'max' => $this->max,
        ];
    }
}
