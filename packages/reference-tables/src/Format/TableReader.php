<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Format;

use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\ReferenceTables\Cell;
use Tilttek\ReferenceTables\Column;
use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Exception\InvalidDocument;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Internal\Fields;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Row;
use Tilttek\ReferenceTables\TableCatalogue;
use Tilttek\RpgSystems\Exception\RpgSystemsException;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;

/**
 * Reads the tables a site declares from a YAML or JSON document of the format `tilttek-reference-tables/1`, every
 * refusal naming its place in the document. It keeps nothing between two calls: one serves every request.
 */
final readonly class TableReader
{
    /** The format a document names in its `format` field. */
    public const string FORMAT = 'tilttek-reference-tables/1';

    /** The largest document read by default, in bytes. */
    public const int MAX_BYTES = 2_097_152;

    private const array DOCUMENT = ['format', 'locale', 'system', 'source', 'tables'];

    private const array TABLE = ['key', 'names', 'description', 'system', 'category', 'page', 'source', 'lookup', 'die', 'columns', 'rows'];

    private const array COLUMN = ['key', 'names', 'kind'];

    private const array SOURCE = ['title', 'licence', 'attribution', 'author', 'url'];

    /**
     * @param int $maxBytes the largest document read, in bytes
     */
    public function __construct(private int $maxBytes = self::MAX_BYTES)
    {
    }

    /**
     * The tables of a document's text.
     *
     * @throws InvalidDocument
     */
    public function parse(string $text, Syntax $syntax): TableCatalogue
    {
        return $this->catalogue($this->decode($text, $syntax));
    }

    /**
     * The tables of a file, `.yaml`, `.yml` or `.json`.
     *
     * @throws InvalidDocument
     */
    public function load(string $path): TableCatalogue
    {
        $syntax = Syntax::ofPath($path);
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;

        if (false === $size) {
            throw InvalidDocument::at('', \sprintf('the file "%s" cannot be read.', basename($path)));
        }

        if ($size > $this->maxBytes) {
            throw InvalidDocument::at('', \sprintf('the file "%s" is longer than %d bytes.', basename($path), $this->maxBytes));
        }

        return $this->parse((string) file_get_contents($path), $syntax);
    }

    /**
     * A document's text decoded, not yet read as tables.
     *
     * @return array<mixed>
     *
     * @throws InvalidDocument
     */
    public function decode(string $text, Syntax $syntax): array
    {
        if (\strlen($text) > $this->maxBytes) {
            throw InvalidDocument::at('', \sprintf('the document is longer than %d bytes.', $this->maxBytes));
        }

        try {
            $document = Syntax::Yaml === $syntax
                ? Yaml::parse($text, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)
                : json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException|ParseException $exception) {
            throw InvalidDocument::at('', \sprintf('the document is not valid %s: %s', strtoupper($syntax->value), $exception->getMessage()), $exception);
        }

        if (!\is_array($document)) {
            throw InvalidDocument::at('', 'the document is not a mapping.');
        }

        return $document;
    }

    /**
     * The tables of a decoded document.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocument
     */
    public function catalogue(array $document): TableCatalogue
    {
        $fields = Fields::of($document, '', self::DOCUMENT);

        if (!$fields->has('format') || self::FORMAT !== $fields->string('format')) {
            throw InvalidDocument::at('format', \sprintf('the document must name the format "%s".', self::FORMAT));
        }

        $locale = $fields->optionalString('locale') ?? 'en';
        $system = $fields->optionalString('system');
        $source = $fields->has('source') ? $this->source($fields->data['source'], 'source') : null;
        $tables = [];

        foreach ($fields->list('tables', TableCatalogue::MAX_TABLES) as $index => $table) {
            $tables[] = $this->table($table, \sprintf('tables[%d]', $index), $locale, $system, $source);
        }

        return $this->build($fields->at('tables'), static fn (): TableCatalogue => new TableCatalogue(...$tables));
    }

    /**
     * @throws InvalidDocument
     */
    private function table(mixed $value, string $path, string $locale, ?string $system, ?Provenance $source): ReferenceTable
    {
        $fields = Fields::of($value, $path, self::TABLE);
        $provenance = $fields->has('source') ? $this->source($fields->data['source'], $fields->at('source')) : $source;

        if (!$provenance instanceof Provenance) {
            throw InvalidDocument::at($fields->at('source'), 'the table has no source, and the document none for it.');
        }

        $lookup = Lookup::tryFrom($fields->optionalString('lookup') ?? Lookup::Range->value)
            ?? throw InvalidDocument::at($fields->at('lookup'), 'the lookup is "key" or "range".');
        $die = $fields->optionalString('die');
        $columns = [];

        foreach ($fields->list('columns', ReferenceTable::MAX_COLUMNS) as $index => $column) {
            $columns[] = $this->column($column, \sprintf('%s.columns[%d]', $path, $index), $locale);
        }

        $kinds = [];

        foreach ($columns as $column) {
            $kinds[$column->key] = $column->kind;
        }

        $percentile = null !== $die && ReferenceTable::PERCENTILE === $this->build($fields->at('die'), static fn (): string => Cell::dice($die)->notation());
        $rows = [];

        foreach ($fields->list('rows', ReferenceTable::MAX_ROWS) as $index => $row) {
            $rows[] = $this->row($row, \sprintf('%s.rows[%d]', $path, $index), $locale, $kinds, $percentile);
        }

        return $this->build($path, static fn (): ReferenceTable => new ReferenceTable(
            $fields->string('key'),
            $fields->texts('names', $locale),
            $provenance,
            $columns,
            $lookup,
            $rows,
            $die,
            $fields->optionalString('system') ?? $system,
            $fields->optionalString('category'),
            $fields->optionalString('page'),
            $fields->has('description') ? $fields->texts('description', $locale) : null,
        ));
    }

    /**
     * @throws InvalidDocument
     */
    private function column(mixed $value, string $path, string $locale): Column
    {
        $fields = Fields::of($value, $path, self::COLUMN);
        $kind = ColumnKind::tryFrom($fields->optionalString('kind') ?? ColumnKind::Text->value)
            ?? throw InvalidDocument::at($fields->at('kind'), 'the kind is "text", "number" or "dice".');

        return $this->build($path, static fn (): Column => new Column($fields->string('key'), $fields->texts('names', $locale), $kind));
    }

    /**
     * @param array<string, ColumnKind> $kinds the kinds of the table's columns, by key
     *
     * @throws InvalidDocument
     */
    private function row(mixed $value, string $path, string $locale, array $kinds, bool $percentile): Row
    {
        $fields = Fields::of($value, $path, ['key', 'range', ...array_keys($kinds)]);
        $cells = [];

        foreach ($kinds as $column => $kind) {
            if ($fields->has($column)) {
                $cells[$column] = $this->cell($fields, $column, $kind, $locale);
            }
        }

        $key = $fields->optionalString('key');
        $range = $fields->has('range') ? $this->build($fields->at('range'), static fn (): Range => Range::parse($fields->string('range'), $percentile)) : null;

        return $this->build($path, static fn (): Row => new Row($key, $range, $cells));
    }

    /**
     * @throws InvalidDocument
     */
    private function cell(Fields $fields, string $column, ColumnKind $kind, string $locale): Cell
    {
        $value = $fields->data[$column];
        $path = $fields->at($column);

        return match ($kind) {
            ColumnKind::Text => $this->build($path, static fn (): Cell => Cell::text(Fields::translations(\is_int($value) ? (string) $value : $value, $path, $locale))),
            ColumnKind::Number => \is_int($value) ? $this->build($path, static fn (): Cell => Cell::number($value)) : throw InvalidDocument::at($path, 'a whole number is expected.'),
            ColumnKind::Dice => $this->build($path, static fn (): Cell => Cell::dice($fields->string($column))),
        };
    }

    /**
     * @throws InvalidDocument
     */
    private function source(mixed $value, string $path): Provenance
    {
        $fields = Fields::of($value, $path, self::SOURCE);
        $licence = Licence::tryFrom($fields->string('licence'))
            ?? throw InvalidDocument::at($fields->at('licence'), \sprintf('the licence is one of %s.', implode(', ', array_column(Licence::cases(), 'value'))));

        return $this->build($path, static fn (): Provenance => new Provenance(
            $fields->string('title'),
            $licence,
            $fields->optionalString('attribution') ?? '',
            $fields->optionalString('author'),
            $fields->optionalString('url'),
        ));
    }

    /**
     * Builds a value, a refusal of the library or of `tilttek/rpg-systems` given back at its place in the document.
     *
     * @template T
     *
     * @param callable(): T $build
     *
     * @return T
     *
     * @throws InvalidDocument
     */
    private function build(string $path, callable $build): mixed
    {
        try {
            return $build();
        } catch (InvalidTable|RpgSystemsException $exception) {
            throw InvalidDocument::at($path, lcfirst($exception->getMessage()), $exception);
        }
    }
}
