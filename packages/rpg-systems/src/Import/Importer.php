<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Import;

use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Format\Fields;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

/**
 * Turns an extract of an open source — tables of records, as JSON or YAML — into a system of the library's format,
 * every record carrying the source the caller states. The source's licence must be one the profile accepts.
 *
 * @phpstan-import-type DifficultyData from ImportProfile
 */
final readonly class Importer
{
    public function __construct(private SystemReader $reader = new SystemReader())
    {
    }

    /**
     * @param string $text the extract, a mapping of table names to lists of records
     *
     * @throws InvalidDocument
     */
    public function importText(ImportProfile $profile, string $text, Syntax $syntax, Provenance $source): GameSystem
    {
        return $this->import($profile, $this->reader->decode($text, $syntax), $source);
    }

    /**
     * @param array<mixed> $extract a mapping of table names to lists of records; the tables the profile does not
     *                              name are left out
     *
     * @throws InvalidDocument
     */
    public function import(ImportProfile $profile, array $extract, Provenance $source): GameSystem
    {
        if (!\in_array($source->licence, $profile->licences, true)) {
            throw InvalidDocument::at('', \sprintf('The profile of "%s" imports material under %s, not %s.', $profile->system, implode(' or ', array_map(static fn (Licence $licence): string => $licence->value, $profile->licences)), $source->licence->value));
        }

        $tables = Fields::of($extract, '');
        $attributes = $profile->attributes instanceof TableMapping ? array_map(
            fn (Fields $record): Attribute => $this->attribute($profile, $profile->attributes, $record, $source),
            $this->rows($tables, $profile->attributes),
        ) : [];
        $terms = [];

        foreach ($profile->terms as $mapping) {
            foreach ($this->rows($tables, $mapping) as $record) {
                $terms[] = $this->term($profile, $mapping, $record, $source);
            }
        }

        $dice = [];

        foreach ($profile->dice as $key => $notation) {
            $dice[] = $tables->record(static fn (): DiceExpression => new DiceExpression($key, $notation, new Translations([$profile->locale => $profile->diceNames[$key] ?? $key]), $source));
        }

        $locale = $profile->locale;
        $variants = [];

        foreach ($profile->variants as $key => $name) {
            $variants[] = $tables->record(static fn (): RuleVariant => new RuleVariant($key, new Translations([$locale => $name]), $source));
        }

        $partyRoles = [];

        foreach ($profile->partyRoles as $key => $name) {
            $partyRoles[] = $tables->record(static fn (): PartyRole => new PartyRole($key, new Translations([$locale => $name]), $source));
        }

        $difficulties = array_map(fn (array $data): Difficulty => $tables->record(fn (): Difficulty => $this->difficulty($data, $locale, $source)), $profile->difficulties);

        return $tables->record(static fn (): GameSystem => new GameSystem(
            $profile->system,
            new Translations([$profile->locale => $profile->name]),
            $source,
            $profile->version,
            $attributes,
            $dice,
            $terms,
            [],
            $variants,
            $difficulties,
            $partyRoles,
        ));
    }

    /**
     * @param DifficultyData $data
     */
    private function difficulty(array $data, string $locale, Provenance $source): Difficulty
    {
        return new Difficulty(
            $data['key'],
            new Translations([$locale => $data['name']]),
            $source,
            isset($data['description']) ? new Translations([$locale => $data['description']]) : new Translations(),
            $data['method'] ?? null,
            array_map(static fn (array $band): DifficultyBand => new DifficultyBand($band['key'], new Translations([$locale => $band['name']]), $band['number'] ?? null), $data['bands'] ?? []),
            $data['numbers'] ?? [],
        );
    }

    /**
     * @return list<Fields>
     *
     * @throws InvalidDocument
     */
    private function rows(Fields $tables, TableMapping $mapping): array
    {
        return array_map(
            static fn (mixed $row, int $index): Fields => Fields::of($row, \sprintf('%s[%d]', $mapping->table, $index)),
            $rows = $tables->list($mapping->table, GameSystem::MAX_RECORDS),
            array_keys($rows),
        );
    }

    private function attribute(ImportProfile $profile, TableMapping $mapping, Fields $record, Provenance $source): Attribute
    {
        $locale = $profile->locale;

        return $record->record(fn (): Attribute => new Attribute(
            $this->key($record, $mapping),
            new Translations([$locale => $record->string($mapping->name)]),
            $source,
            null === $mapping->abbreviation ? new Translations() : new Translations([$locale => $record->string($mapping->abbreviation)]),
            $this->description($record, $mapping, $locale),
            $profile->attributeMinimum,
            $profile->attributeMaximum,
        ));
    }

    private function term(ImportProfile $profile, TableMapping $mapping, Fields $record, Provenance $source): Term
    {
        return $record->record(fn (): Term => new Term(
            ($mapping->category ?? 'term').'.'.$this->key($record, $mapping),
            new Translations([$profile->locale => $record->string($mapping->name)]),
            $source,
            $mapping->category,
            $this->description($record, $mapping, $profile->locale),
        ));
    }

    /**
     * The record's key, lowercased, every character a key cannot hold turned into a hyphen: `Sleight of Hand` gives
     * `sleight-of-hand`.
     */
    private function key(Fields $record, TableMapping $mapping): string
    {
        return trim((string) preg_replace('/[^a-z0-9_.]+/', '-', strtolower($record->string($mapping->key))), '-.');
    }

    /**
     * The description, a text or a list of paragraphs joined by a blank line; none when the field is absent.
     */
    private function description(Fields $record, TableMapping $mapping, string $locale): Translations
    {
        $field = $mapping->description;

        if (null === $field || !$record->has($field)) {
            return new Translations();
        }

        $paragraphs = \is_array($record->value($field))
            ? array_map(static fn (mixed $paragraph): string => \is_string($paragraph) ? $paragraph : throw InvalidDocument::at($record->at($field), 'a text or a list of texts is expected.'), $record->list($field, 100))
            : [$record->string($field)];

        return new Translations([$locale => implode("\n\n", $paragraphs)]);
    }
}
