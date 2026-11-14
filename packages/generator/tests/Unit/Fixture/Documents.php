<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Fixture;

use PHPUnit\Framework\Assert;
use Tilttek\Generator\Import\Imported;

/**
 * Reads the documents an import made, and results as texts.
 */
final class Documents
{
    private function __construct()
    {
    }

    /**
     * The templates of an imported generator.
     *
     * @return list<mixed>
     */
    public static function templates(Imported $imported, string $generator): array
    {
        return self::sequence($imported->document['generators'][$generator] ?? null, 'templates');
    }

    /**
     * The entries of an imported list.
     *
     * @return list<mixed>
     */
    public static function entries(Imported $imported, string $list): array
    {
        return self::sequence($imported->document['lists'][$list] ?? null, 'entries');
    }

    public static function text(mixed $value): string
    {
        Assert::assertIsString($value);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    public static function texts(array $values): array
    {
        return array_values(array_map(self::text(...), $values));
    }

    /**
     * @return list<mixed>
     */
    private static function sequence(mixed $map, string $key): array
    {
        Assert::assertIsArray($map);
        $sequence = $map[$key] ?? null;
        Assert::assertIsList($sequence);

        return $sequence;
    }
}
