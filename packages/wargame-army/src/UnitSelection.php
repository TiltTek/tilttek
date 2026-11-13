<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidArmyList;

/**
 * A unit chosen in a detachment: the code of its catalogue entry, its number of models and the options taken, each
 * with the number of times. Whether the catalogue allows it is the validator's question, not this value's.
 */
final readonly class UnitSelection
{
    /**
     * The most distinct options of a selection.
     */
    public const int MAX_OPTIONS = 100;

    public string $unit;

    public int $models;

    /**
     * The codes of the options taken, each with its count, 1 to 1 000, in the order they were taken.
     *
     * @var array<array-key, int>
     */
    public array $options;

    /**
     * @param array<array-key, int> $options
     *
     * @throws InvalidArmyList when a code or a number is refused
     */
    public function __construct(string $unit, int $models = 1, array $options = [])
    {
        $this->unit = Identifiers::code($unit, 'unit', Fault::ArmyList);
        $this->models = Identifiers::bounded($models, 1, Catalogue::MAX_COUNT, 'the models of a unit', fault: Fault::ArmyList);

        if (\count($options) > self::MAX_OPTIONS) {
            throw InvalidArmyList::because(\sprintf('A unit takes at most %d distinct options.', self::MAX_OPTIONS));
        }

        $checked = [];

        foreach ($options as $option => $count) {
            $option = Identifiers::code((string) $option, 'option', Fault::ArmyList);
            $checked[$option] = Identifiers::bounded($count, 1, Catalogue::MAX_COUNT, 'the count of the option "%s"', $option, fault: Fault::ArmyList);
        }

        $this->options = $checked;
    }

    /**
     * @throws InvalidArmyList when the array does not read as a unit selection
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::ArmyList);
        $object = $input->object($data, 'unit selection', ['unit'], ['models', 'options']);

        return new self(
            $input->string($object, 'unit', 'unit selection'),
            $input->int($object, 'models', 'unit selection', 1),
            $input->counts($object, 'options', 'unit selection', self::MAX_OPTIONS),
        );
    }

    /**
     * @throws InvalidArmyList when the number is refused
     */
    public function withModels(int $models): self
    {
        return new self($this->unit, $models, $this->options);
    }

    /**
     * The selection taking the option that many times; 0 drops it.
     *
     * @throws InvalidArmyList when the code or the count is refused
     */
    public function withOption(string $option, int $count = 1): self
    {
        $options = $this->options;

        if (0 === $count) {
            unset($options[$option]);
        } else {
            $options[$option] = $count;
        }

        return new self($this->unit, $this->models, $options);
    }

    /**
     * @return array{unit: string, models: int, options: array<array-key, int>}
     */
    public function toArray(): array
    {
        return ['unit' => $this->unit, 'models' => $this->models, 'options' => $this->options];
    }
}
