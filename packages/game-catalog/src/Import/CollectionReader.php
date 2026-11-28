<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Closure;
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Exception\InvalidCollectionFile;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\IdentifierScheme;

/**
 * Reads a collection file a member exported: CSV after RFC 4180, a header row first, UTF-8 with or without its byte
 * order mark. The file is refused as a whole only when it cannot be read at all — over its size, too many rows or
 * columns, not UTF-8, without the columns asked for; a row that cannot be read is left out with its reason, and the
 * reader goes on.
 */
final readonly class CollectionReader
{
    private const array YES = ['1', 'yes', 'true', 'x'];

    private const array NO = ['', '0', 'no', 'false'];

    /**
     * @throws InvalidCollectionFile when the file cannot be read as a whole
     */
    public function read(string $contents, Columns $columns, Limits $limits = new Limits()): CollectionFile
    {
        if (\strlen($contents) > $limits->bytes) {
            throw InvalidCollectionFile::because(\sprintf('A collection file is at most %d bytes.', $limits->bytes));
        }

        if (str_starts_with($contents, "\u{FEFF}")) {
            $contents = substr($contents, 3);
        }

        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw InvalidCollectionFile::because('A collection file is UTF-8.');
        }

        $file = fopen('php://memory', 'w+');
        \assert(false !== $file);
        fwrite($file, $contents);
        rewind($file);

        $next = static function () use ($file, $columns): ?array {
            $record = fgetcsv($file, null, $columns->delimiter, '"', '');

            return false === $record ? null : $record;
        };

        // The stream is freed with the last reference to it, when the method returns or throws.
        return $this->rows($next, $columns, $limits);
    }

    /**
     * @param Closure(): ?list<?string> $next the next record of the file, null at its end
     */
    private function rows(Closure $next, Columns $columns, Limits $limits): CollectionFile
    {
        $header = $next();

        if (null === $header || [null] === $header) {
            throw InvalidCollectionFile::because('A collection file starts with a header row.');
        }

        if (\count($header) > $limits->columns) {
            throw InvalidCollectionFile::because(\sprintf('A collection file has at most %d columns.', $limits->columns));
        }

        $positions = $this->positions($header, $columns);
        $rows = [];
        $errors = [];
        $more = 0;
        $seen = [];
        $line = 1;

        while (null !== ($record = $next())) {
            ++$line;

            if ([null] === $record) {
                continue;
            }

            if ($line - 1 > $limits->rows) {
                throw InvalidCollectionFile::because(\sprintf('A collection file has at most %d rows.', $limits->rows));
            }

            try {
                $row = $this->row($line, $record, $positions, $columns, $limits);

                if (isset($seen[$row->identifier->key()])) {
                    throw InvalidEntry::because(\sprintf('The game %s is on row %d already.', $row->identifier->key(), $seen[$row->identifier->key()]));
                }

                $seen[$row->identifier->key()] = $line;
                $rows[] = $row;
            } catch (InvalidEntry $error) {
                if (\count($errors) < $limits->errors) {
                    $errors[] = new RowError($line, $error->getMessage());
                } else {
                    ++$more;
                }
            }
        }

        return new CollectionFile($rows, $errors, $more);
    }

    /**
     * @param list<?string> $header
     *
     * @return array{identifier: int, scheme: ?int, priority: ?int, plays: ?int, statuses: array<int, CollectionStatus>}
     */
    private function positions(array $header, Columns $columns): array
    {
        $index = [];

        foreach ($header as $position => $name) {
            $index[Columns::header((string) $name)] ??= $position;
        }

        $find = static function (?string $name, bool $required) use ($index): ?int {
            if (null === $name) {
                return null;
            }

            if ($required && !isset($index[$name])) {
                throw InvalidCollectionFile::because(\sprintf('The collection file has no column "%s".', $name));
            }

            return $index[$name] ?? null;
        };

        $statuses = [];

        foreach ($columns->statuses as $name => $status) {
            $position = $find($name, false);

            if (null !== $position) {
                $statuses[$position] = $status;
            }
        }

        return [
            'identifier' => (int) $find($columns->identifier, true),
            'scheme' => $find($columns->schemeColumn, true),
            'priority' => $find($columns->priority, false),
            'plays' => $find($columns->plays, false),
            'statuses' => $statuses,
        ];
    }

    /**
     * @param list<?string>                                                                                             $record
     * @param array{identifier: int, scheme: ?int, priority: ?int, plays: ?int, statuses: array<int, CollectionStatus>} $positions
     *
     * @throws InvalidEntry when the row cannot be read
     */
    private function row(int $line, array $record, array $positions, Columns $columns, Limits $limits): CollectionRow
    {
        if (\count($record) > $limits->columns) {
            throw InvalidEntry::because(\sprintf('A row has at most %d cells.', $limits->columns));
        }

        foreach ($record as $cell) {
            if (\strlen((string) $cell) > $limits->cellBytes) {
                throw InvalidEntry::because(\sprintf('A cell is at most %d bytes.', $limits->cellBytes));
            }
        }

        $cell = static fn (?int $position): string => null === $position ? '' : trim($record[$position] ?? '');
        $scheme = $columns->scheme ?? IdentifierScheme::tryFrom(strtolower($cell($positions['scheme'])));

        if (null === $scheme) {
            throw InvalidEntry::because('The database of the game is boardgamegeek, rpggeek or wikidata.');
        }

        $statuses = [];

        foreach ($positions['statuses'] as $position => $status) {
            $value = strtolower($cell($position));

            if (\in_array($value, self::YES, true)) {
                $statuses[$status->value] = $status;
            } elseif (!\in_array($value, self::NO, true)) {
                throw InvalidEntry::because(\sprintf('The status %s is 1 or 0, yes or no, true or false, x or empty.', $status->value));
            }
        }

        return new CollectionRow(
            $line,
            new ExternalId($scheme, $cell($positions['identifier'])),
            array_values(array_filter(CollectionStatus::cases(), static fn (CollectionStatus $status): bool => isset($statuses[$status->value]))),
            $this->number($cell($positions['priority']), 1, 5, 'The priority of a wish'),
            $this->number($cell($positions['plays']), 0, 100_000, 'The games played'),
        );
    }

    private function number(string $value, int $min, int $max, string $what): ?int
    {
        if ('' === $value) {
            return null;
        }

        $number = (int) $value;

        if (1 !== preg_match('/^[0-9]{1,6}\z/', $value) || $number < $min || $number > $max) {
            throw InvalidEntry::because(\sprintf('%s is a whole number from %d to %d.', $what, $min, $max));
        }

        return $number;
    }
}
