<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * A unit a catalogue offers: its categories, its points for its fewest models, the points of each model beyond them,
 * and its option groups.
 */
final readonly class UnitEntry
{
    /**
     * The most categories of a unit.
     */
    public const int MAX_CATEGORIES = 20;

    /**
     * The most option groups of a unit.
     */
    public const int MAX_GROUPS = 50;

    public string $code;

    public string $name;

    /** @var list<string> */
    public array $categories;

    public int $points;

    public int $minModels;

    public int $maxModels;

    public int $pointsPerModel;

    /** @var list<OptionGroup> */
    public array $optionGroups;

    /**
     * Every option of the unit by its code, for a lookup in constant time.
     *
     * @var array<string, Option>
     */
    private array $options;

    /**
     * @param list<string>      $categories     the categories of the unit, from the one that fills a detachment's slot
     *                                          first
     * @param int               $points         the points of the unit with its fewest models, 0 to 100 000
     * @param int               $pointsPerModel the points of each model beyond the fewest, 0 to 100 000
     * @param list<OptionGroup> $optionGroups   the option groups, each option code once in the unit
     *
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(
        string $code,
        string $name,
        array $categories = [],
        int $points = 0,
        int $minModels = 1,
        ?int $maxModels = null,
        int $pointsPerModel = 0,
        array $optionGroups = [],
    ) {
        $this->code = Identifiers::code($code, 'unit');
        $this->name = Identifiers::name($name, 'unit');
        $this->points = Identifiers::bounded($points, 0, Catalogue::MAX_POINTS, 'the points of the unit "%s"', $code);
        $this->minModels = Identifiers::bounded($minModels, 1, Catalogue::MAX_COUNT, 'the fewest models of the unit "%s"', $code);
        $this->maxModels = Identifiers::bounded($maxModels ?? $minModels, $minModels, Catalogue::MAX_COUNT, 'the most models of the unit "%s"', $code);
        $this->pointsPerModel = Identifiers::bounded($pointsPerModel, 0, Catalogue::MAX_POINTS, 'the points of each model of the unit "%s"', $code);

        if (\count($categories) > self::MAX_CATEGORIES) {
            throw InvalidCatalogue::because(\sprintf('The unit "%s" has at most %d categories.', $code, self::MAX_CATEGORIES));
        }

        foreach ($categories as $index => $category) {
            Identifiers::code($category, 'category');

            if (\in_array($category, \array_slice($categories, 0, $index), true)) {
                throw InvalidCatalogue::because(\sprintf('The unit "%s" has the category "%s" twice.', $code, $category));
            }
        }

        if (\count($optionGroups) > self::MAX_GROUPS) {
            throw InvalidCatalogue::because(\sprintf('The unit "%s" has at most %d option groups.', $code, self::MAX_GROUPS));
        }

        $options = [];

        foreach ($optionGroups as $group) {
            foreach ($group->options as $option) {
                if (isset($options[$option->code])) {
                    throw InvalidCatalogue::because(\sprintf('The unit "%s" has two options "%s".', $code, $option->code));
                }

                $options[$option->code] = $option;
            }
        }

        $this->categories = $categories;
        $this->optionGroups = $optionGroups;
        $this->options = $options;
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a unit
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'unit', ['code', 'name'], ['categories', 'points', 'min_models', 'max_models', 'points_per_model', 'option_groups']);

        return new self(
            $input->string($object, 'code', 'unit'),
            $input->string($object, 'name', 'unit'),
            $input->strings($object, 'categories', 'unit', self::MAX_CATEGORIES),
            $input->int($object, 'points', 'unit', 0),
            $input->int($object, 'min_models', 'unit', 1),
            $input->nullableInt($object, 'max_models', 'unit'),
            $input->int($object, 'points_per_model', 'unit', 0),
            array_map(OptionGroup::fromArray(...), $input->list($object, 'option_groups', 'unit', self::MAX_GROUPS)),
        );
    }

    /**
     * The option of that code, in any group, or null.
     */
    public function option(string $code): ?Option
    {
        return $this->options[$code] ?? null;
    }

    public function hasCategory(string $category): bool
    {
        return \in_array($category, $this->categories, true);
    }

    /**
     * @return array{code: string, name: string, categories: list<string>, points: int, min_models: int, max_models: int, points_per_model: int, option_groups: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'categories' => $this->categories,
            'points' => $this->points,
            'min_models' => $this->minModels,
            'max_models' => $this->maxModels,
            'points_per_model' => $this->pointsPerModel,
            'option_groups' => array_map(static fn (OptionGroup $group): array => $group->toArray(), $this->optionGroups),
        ];
    }
}
