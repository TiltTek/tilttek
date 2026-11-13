<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * A choice among options: the fewest and the most selections a unit makes in it, every option counted as many times
 * as it is taken — "one weapon", "up to two upgrades".
 */
final readonly class OptionGroup
{
    /**
     * The most options of a group.
     */
    public const int MAX_OPTIONS = 100;

    public string $code;

    public string $name;

    public int $min;

    public int $max;

    /** @var array<string, Option> */
    public array $options;

    /**
     * @param list<Option> $options 1 to 100 options, each code once
     *
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(string $code, string $name, array $options, int $min = 0, int $max = 1)
    {
        $this->code = Identifiers::code($code, 'option group');
        $this->name = Identifiers::name($name, 'option group');
        $this->min = Identifiers::bounded($min, 0, Catalogue::MAX_COUNT, 'the fewest selections in the group "%s"', $code);
        $this->max = Identifiers::bounded($max, max(1, $min), Catalogue::MAX_COUNT, 'the most selections in the group "%s"', $code);

        if ([] === $options || \count($options) > self::MAX_OPTIONS) {
            throw InvalidCatalogue::because(\sprintf('The group "%s" has 1 to %d options.', $code, self::MAX_OPTIONS));
        }

        $byCode = [];

        foreach ($options as $option) {
            if (isset($byCode[$option->code])) {
                throw InvalidCatalogue::because(\sprintf('The group "%s" has two options "%s".', $code, $option->code));
            }

            $byCode[$option->code] = $option;
        }

        $this->options = $byCode;
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a group
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'option group', ['code', 'name', 'options'], ['min', 'max']);

        return new self(
            $input->string($object, 'code', 'option group'),
            $input->string($object, 'name', 'option group'),
            array_map(Option::fromArray(...), $input->list($object, 'options', 'option group', self::MAX_OPTIONS)),
            $input->int($object, 'min', 'option group', 0),
            $input->int($object, 'max', 'option group', 1),
        );
    }

    /**
     * @return array{code: string, name: string, min: int, max: int, options: list<array{code: string, name: string, points: int, max: int, per_model: bool}>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'min' => $this->min,
            'max' => $this->max,
            'options' => array_values(array_map(static fn (Option $option): array => $option->toArray(), $this->options)),
        ];
    }
}
