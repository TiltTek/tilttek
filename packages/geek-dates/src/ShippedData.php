<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

use Tilttek\GeekDates\Exception\InvalidEntry;

/**
 * The data the package ships: `data/dates.php`, one row per entry, and `data/VERSION`, the version of that data.
 */
final class ShippedData
{
    /**
     * The rows of the data file, as `EntryReader` reads them.
     *
     * @return list<array<mixed>>
     */
    public static function rows(): array
    {
        $data = require self::directory().'/dates.php';
        $rows = [];

        foreach (\is_array($data) ? $data : throw InvalidEntry::because('?', 'is a data file that does not return an array.') as $row) {
            $rows[] = \is_array($row) ? $row : throw InvalidEntry::because('?', 'is a row of the data file that is not an array.');
        }

        return $rows;
    }

    /**
     * @return list<Entry>
     */
    public static function entries(): array
    {
        return new EntryReader()->readAll(self::rows());
    }

    /**
     * The version of the data, such as `2026.11.21`: the day its entries last changed.
     */
    public static function version(): string
    {
        return trim((string) file_get_contents(self::directory().'/VERSION'));
    }

    public static function directory(): string
    {
        return \dirname(__DIR__).'/data';
    }
}
