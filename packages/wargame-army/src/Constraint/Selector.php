<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Constraint;

use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Identifiers;
use Tilttek\WargameArmy\UnitEntry;

/**
 * The units a constraint counts: one unit of the catalogue, or every unit of a category.
 */
final readonly class Selector
{
    private function __construct(
        public ?string $unit,
        public ?string $category,
    ) {
    }

    /**
     * @throws InvalidCatalogue when the code is refused
     */
    public static function unit(string $code): self
    {
        return new self(Identifiers::code($code, 'unit'), null);
    }

    /**
     * @throws InvalidCatalogue when the code is refused
     */
    public static function category(string $code): self
    {
        return new self(null, Identifiers::code($code, 'category'));
    }

    /**
     * The selector of a constraint read from an array: its `unit` or its `category`, never both.
     *
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue when the array holds neither or both, or a code is refused
     */
    public static function fromArray(array $object): self
    {
        $unit = $object['unit'] ?? null;
        $category = $object['category'] ?? null;

        if (\is_string($unit) && null === $category) {
            return self::unit($unit);
        }

        if (\is_string($category) && null === $unit) {
            return self::category($category);
        }

        throw InvalidCatalogue::because('A constraint counts either a "unit" or a "category", given as a string.');
    }

    public function matches(UnitEntry $unit): bool
    {
        return null === $this->unit ? $unit->hasCategory((string) $this->category) : $unit->code === $this->unit;
    }

    /**
     * The units selected, as messages show them: the unit's name, or its category.
     */
    public function label(Catalogue $catalogue): string
    {
        if (null === $this->unit) {
            return \sprintf('units of the category "%s"', $this->category);
        }

        return \sprintf('"%s"', $catalogue->unit($this->unit)->name ?? $this->unit);
    }

    /**
     * @return array{unit: string}|array{category: string}
     */
    public function toArray(): array
    {
        return null === $this->unit ? ['category' => (string) $this->category] : ['unit' => $this->unit];
    }
}
