<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents;

use Tilttek\RpgCalendarEvents\Exception\RpgCalendarEventsException;

/**
 * Checks rows of entries, shipped or an application's own, and lists every problem instead of stopping at the first:
 * a row that is not an entry — a rule that does not read, no source, no date of verification —, or an identifier
 * found twice.
 */
final class DataCheck
{
    /**
     * @param iterable<mixed> $rows
     *
     * @return list<string> the problems, rows counted from 1; empty when every row is an entry
     */
    public function problems(iterable $rows): array
    {
        $problems = [];
        $seen = [];
        $reader = new EntryReader();
        $position = 0;

        foreach ($rows as $row) {
            ++$position;

            if (!\is_array($row)) {
                $problems[] = \sprintf('Row %d is not an array.', $position);

                continue;
            }

            try {
                $entry = $reader->read($row);
            } catch (RpgCalendarEventsException $invalid) {
                $problems[] = \sprintf('Row %d: %s', $position, $invalid->getMessage());

                continue;
            }

            if (isset($seen[$entry->id])) {
                $problems[] = \sprintf('Row %d: the identifier "%s" is already row %d.', $position, $entry->id, $seen[$entry->id]);
            }

            $seen[$entry->id] ??= $position;
        }

        return $problems;
    }
}
