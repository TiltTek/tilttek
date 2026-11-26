<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Comparison;
use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Name;

/**
 * Reads back the operations `Operation::toArray()` writes, from a declaration: every field checked, a refusal for
 * anything else.
 */
final class Operations
{
    public const int MAX_OPERANDS = 16;

    /**
     * @throws InvalidSheet when the data is not an operation `toArray()` could have written
     */
    public static function fromArray(mixed $data): Operation
    {
        if (!\is_array($data)) {
            throw InvalidSheet::because('An operation is an object.');
        }

        return match ($data['type'] ?? null) {
            'sum' => new Sum(self::keys($data)),
            'product' => new Product(self::keys($data), self::int($data, 'factor', 1)),
            'majority' => new Majority(
                self::string($data, 'of'),
                self::points($data),
                Direction::tryFrom(self::string($data, 'prefer', 'most')) ?? throw self::invalid('prefer'),
                null === ($data['at_least'] ?? null) ? null : self::int($data, 'at_least'),
            ),
            'bonus' => self::bonus($data),
            default => throw InvalidSheet::because('An operation is sum, product, majority or bonus.'),
        };
    }

    /**
     * @param list<string> $categories
     *
     * @return non-empty-list<string>
     *
     * @internal
     */
    public static function categories(array $categories, string $what): array
    {
        if ([] === $categories || \count($categories) > self::MAX_OPERANDS) {
            throw InvalidSheet::because(\sprintf('%s reads 1 to %d categories.', $what, self::MAX_OPERANDS));
        }

        foreach ($categories as $category) {
            Name::key($category, 'A category key');
        }

        return $categories;
    }

    /**
     * @internal
     */
    public static function constant(int $value, string $what): void
    {
        if ($value < -Operation::MAX_CONSTANT || $value > Operation::MAX_CONSTANT) {
            throw InvalidSheet::because(\sprintf('%s is between -%2$d and %2$d.', $what, Operation::MAX_CONSTANT));
        }
    }

    /**
     * @param array<mixed> $data
     */
    private static function bonus(array $data): Bonus
    {
        $conditions = array_values(array_filter(Comparison::cases(), static fn (Comparison $comparison): bool => \array_key_exists($comparison->value, $data)));

        if (1 !== \count($conditions)) {
            throw InvalidSheet::because('A bonus has one condition: at_least, at_most or equals.');
        }

        return new Bonus(self::string($data, 'of'), $conditions[0], self::int($data, $conditions[0]->value), self::int($data, 'points'));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private static function keys(array $data): array
    {
        $of = $data['of'] ?? null;

        if (!\is_array($of) || !array_is_list($of)) {
            throw self::invalid('of');
        }

        return array_map(static fn (mixed $key): string => \is_string($key) ? $key : throw self::invalid('of'), $of);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<int>
     */
    private static function points(array $data): array
    {
        $points = $data['points'] ?? null;

        if (!\is_array($points) || !array_is_list($points)) {
            throw self::invalid('points');
        }

        return array_map(static fn (mixed $point): int => \is_int($point) ? $point : throw self::invalid('points'), $points);
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $field, ?string $default = null): string
    {
        $value = $data[$field] ?? $default;

        return \is_string($value) ? $value : throw self::invalid($field);
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $field, ?int $default = null): int
    {
        $value = $data[$field] ?? $default;

        return \is_int($value) ? $value : throw self::invalid($field);
    }

    private static function invalid(string $field): InvalidSheet
    {
        return InvalidSheet::because(\sprintf('The field "%s" of the operation is missing or of the wrong type.', $field));
    }
}
