<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Format;

use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\UnknownRecord;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Localisation;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Term;

/**
 * Reads the documents of the library's format, in YAML or JSON: a system with its records, or the localisation of a
 * system in one more language. Every record carries a source, its own or the document's.
 */
final readonly class SystemReader
{
    /** The format a document declares: `format: tilttek-rpg-systems/1`. */
    public const string FORMAT = 'tilttek-rpg-systems/1';

    private const array SYSTEM = ['format', 'kind', 'locale', 'system', 'source', 'attributes', 'dice', 'terms', 'variants', 'difficulties', 'roles', 'translations'];

    private const array LOCALISATION = ['format', 'kind', 'locale', 'system', 'source', 'name', 'attributes', 'dice', 'terms', 'variants', 'difficulties', 'roles'];

    private const array SOURCE = ['title', 'licence', 'attribution', 'author', 'url'];

    /**
     * @param int $maxBytes the longest document read, in bytes
     */
    public function __construct(private int $maxBytes = 2_000_000)
    {
    }

    /**
     * The systems of several files, `.yaml`, `.yml` or `.json`: the systems first, then each localisation laid over
     * the system it names, in the order of the files.
     *
     * @throws InvalidDocument
     */
    public function load(string ...$paths): Systems
    {
        $systems = [];
        $localisations = [];

        foreach ($paths as $path) {
            $size = is_file($path) ? filesize($path) : false;

            if (false === $size || $size > $this->maxBytes) {
                throw InvalidDocument::at($path, false === $size ? 'the file cannot be read.' : \sprintf('the file is longer than %d bytes.', $this->maxBytes));
            }

            $document = $this->decode((string) file_get_contents($path), Syntax::ofPath($path));

            if ('localisation' === ($document['kind'] ?? null)) {
                $localisations[] = [$path, $document];

                continue;
            }

            $system = $this->system($document);
            $systems[$system->key] = $system;
        }

        foreach ($localisations as [$path, $document]) {
            $key = Fields::of($document, '')->string('system');
            $system = $systems[$key] ?? throw InvalidDocument::at($path, \sprintf('the localisation names the system "%s", which no file holds.', $key));
            $systems[$key] = $this->localise($system, $document);
        }

        return new Systems(...array_values($systems));
    }

    /**
     * A text decoded: YAML without objects nor tags, or JSON, at most `maxBytes` long.
     *
     * @return array<mixed>
     *
     * @throws InvalidDocument
     */
    public function decode(string $text, Syntax $syntax): array
    {
        if (\strlen($text) > $this->maxBytes) {
            throw InvalidDocument::at('', \sprintf('The document is longer than %d bytes.', $this->maxBytes));
        }

        try {
            $document = Syntax::Yaml === $syntax
                ? Yaml::parse($text, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)
                : json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException|ParseException $exception) {
            throw InvalidDocument::at('', \sprintf('The document is not valid %s: %s', strtoupper($syntax->value), $exception->getMessage()), $exception);
        }

        if (!\is_array($document)) {
            throw InvalidDocument::at('', 'The document is not a mapping.');
        }

        return $document;
    }

    /**
     * A system from a decoded document.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocument
     */
    public function system(array $document): GameSystem
    {
        $fields = $this->header($document, 'system', self::SYSTEM);
        $locale = $this->locale($fields);
        $source = $this->source($fields->map('source'));
        $system = $fields->map('system')->only(['key', 'names', 'version']);

        return $fields->record(fn (): GameSystem => new GameSystem(
            $system->string('key'),
            $system->texts('names', $locale),
            $source,
            $system->string('version', required: false),
            $this->records($fields, 'attributes', $source, $locale, $this->attribute(...)),
            $this->records($fields, 'dice', $source, $locale, $this->dice(...)),
            $this->records($fields, 'terms', $source, $locale, $this->term(...)),
            $this->translationSources($fields),
            $this->records($fields, 'variants', $source, $locale, $this->variant(...)),
            $this->records($fields, 'difficulties', $source, $locale, $this->difficulty(...)),
            $this->records($fields, 'roles', $source, $locale, $this->partyRole(...)),
        ));
    }

    /**
     * A localisation from a decoded document.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocument
     */
    public function localisation(array $document): Localisation
    {
        $fields = $this->header($document, 'localisation', self::LOCALISATION);
        $fields->string('system');

        $source = $this->source($fields->map('source'));
        $attributes = [];
        $dice = [];

        foreach ($fields->map('attributes')->names() as $key) {
            $texts = $fields->map('attributes')->map($key)->only(['name', 'abbreviation', 'description']);
            $attributes[$key] = array_filter(['name' => $texts->string('name', false), 'abbreviation' => $texts->string('abbreviation', false), 'description' => $texts->string('description', false)], is_string(...));
        }

        foreach ($fields->map('dice')->names() as $key) {
            $dice[$key] = $fields->map('dice')->string($key);
        }

        $terms = $this->namedTexts($fields, 'terms');
        $variants = $this->namedTexts($fields, 'variants');
        $roles = $this->namedTexts($fields, 'roles');
        $difficulties = [];

        foreach ($fields->map('difficulties')->names() as $key) {
            $texts = $fields->map('difficulties')->map($key)->only(['name', 'description', 'bands']);
            $bands = [];

            foreach ($texts->map('bands')->names() as $band) {
                $bands[$band] = $texts->map('bands')->string($band);
            }

            $difficulties[$key] = array_filter(['name' => $texts->string('name', false), 'description' => $texts->string('description', false), 'bands' => $bands], static fn (mixed $value): bool => null !== $value && [] !== $value);
        }

        return $fields->record(static fn (): Localisation => new Localisation($fields->string('locale'), $source, $fields->string('name', false), $attributes, $dice, $terms, $variants, $difficulties, $roles));
    }

    /**
     * A system with a localisation document laid over it.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocument when the document is not a localisation of this system, or names a record it lacks
     */
    public function localise(GameSystem $system, array $document): GameSystem
    {
        $localisation = $this->localisation($document);
        $key = Fields::of($document, '')->string('system');

        if ($key !== $system->key) {
            throw InvalidDocument::at('system', \sprintf('the localisation is of the system "%s", not "%s".', $key, $system->key));
        }

        try {
            return $system->localised($localisation);
        } catch (UnknownRecord $unknownRecord) {
            throw InvalidDocument::at('', $unknownRecord->getMessage(), $unknownRecord);
        }
    }

    /**
     * A provenance from a decoded mapping of `title`, `licence`, `attribution`, `author` and `url`, as a document's
     * `source` holds it.
     *
     * @param array<mixed> $source
     * @param string       $path   the place of the mapping, for the messages of the refusals
     *
     * @throws InvalidDocument
     */
    public function provenance(array $source, string $path = 'source'): Provenance
    {
        return $this->source(Fields::of($source, $path));
    }

    /**
     * @param array<mixed> $document
     * @param list<string> $allowed
     *
     * @throws InvalidDocument
     */
    private function header(array $document, string $kind, array $allowed): Fields
    {
        $fields = Fields::of($document, '')->only($allowed);

        if (self::FORMAT !== $fields->string('format', required: false)) {
            throw InvalidDocument::at('format', \sprintf('the document must declare "format: %s".', self::FORMAT));
        }

        if ($kind !== ($fields->string('kind', required: false) ?? 'system')) {
            throw InvalidDocument::at('kind', \sprintf('a document of the kind "%s" is expected.', $kind));
        }

        return $fields;
    }

    private function locale(Fields $fields): string
    {
        return $fields->string('locale', required: false) ?? 'en';
    }

    /**
     * @throws InvalidDocument
     */
    private function source(Fields $source): Provenance
    {
        $source->only(self::SOURCE);
        $licence = Licence::tryFrom($source->string('licence'))
            ?? throw InvalidDocument::at($source->at('licence'), \sprintf('the licence must be one of %s.', implode(', ', array_map(static fn (Licence $licence): string => $licence->value, Licence::cases()))));

        return $source->record(static fn (): Provenance => new Provenance(
            $source->string('title'),
            $licence,
            $source->string('attribution', required: false) ?? '',
            $source->string('author', required: false),
            $source->string('url', required: false),
        ));
    }

    /**
     * @template T of Attribute|DiceExpression|Term|RuleVariant|Difficulty|PartyRole
     *
     * @param callable(Fields, Provenance, string): T $read
     *
     * @return list<T>
     *
     * @throws InvalidDocument
     */
    private function records(Fields $fields, string $name, Provenance $source, string $locale, callable $read): array
    {
        $records = [];

        foreach ($fields->list($name, GameSystem::MAX_RECORDS) as $index => $item) {
            $record = Fields::of($item, \sprintf('%s[%d]', $name, $index));
            $records[] = $read($record, $record->has('source') ? $this->source($record->map('source')) : $source, $locale);
        }

        return $records;
    }

    private function attribute(Fields $fields, Provenance $source, string $locale): Attribute
    {
        $fields->only(['key', 'names', 'abbreviations', 'descriptions', 'minimum', 'maximum', 'source']);

        return $fields->record(static fn (): Attribute => new Attribute(
            $fields->string('key'),
            $fields->texts('names', $locale),
            $source,
            $fields->texts('abbreviations', $locale),
            $fields->texts('descriptions', $locale),
            $fields->int('minimum'),
            $fields->int('maximum'),
        ));
    }

    private function dice(Fields $fields, Provenance $source, string $locale): DiceExpression
    {
        $fields->only(['key', 'notation', 'names', 'source']);

        return $fields->record(static fn (): DiceExpression => new DiceExpression($fields->string('key'), $fields->string('notation'), $fields->texts('names', $locale), $source));
    }

    private function term(Fields $fields, Provenance $source, string $locale): Term
    {
        $fields->only(['key', 'names', 'category', 'descriptions', 'source']);

        return $fields->record(static fn (): Term => new Term(
            $fields->string('key'),
            $fields->texts('names', $locale),
            $source,
            $fields->string('category', required: false),
            $fields->texts('descriptions', $locale),
        ));
    }

    private function variant(Fields $fields, Provenance $source, string $locale): RuleVariant
    {
        $fields->only(['key', 'names', 'descriptions', 'source']);

        return $fields->record(static fn (): RuleVariant => new RuleVariant($fields->string('key'), $fields->texts('names', $locale), $source, $fields->texts('descriptions', $locale)));
    }

    private function partyRole(Fields $fields, Provenance $source, string $locale): PartyRole
    {
        $fields->only(['key', 'names', 'descriptions', 'source']);

        return $fields->record(static fn (): PartyRole => new PartyRole($fields->string('key'), $fields->texts('names', $locale), $source, $fields->texts('descriptions', $locale)));
    }

    private function difficulty(Fields $fields, Provenance $source, string $locale): Difficulty
    {
        $fields->only(['key', 'names', 'descriptions', 'method', 'bands', 'numbers', 'source']);
        $bands = [];

        foreach ($fields->list('bands', Difficulty::MAX_BANDS) as $index => $item) {
            $band = Fields::of($item, \sprintf('%s[%d]', $fields->at('bands'), $index))->only(['key', 'names', 'number']);
            $bands[] = $band->record(static fn (): DifficultyBand => new DifficultyBand($band->string('key'), $band->texts('names', $locale), $band->int('number')));
        }

        // The labels of the tables are levels and challenges as often as names: `1`, `1/8`, `-4`. They are read as
        // they were decoded, a whole-number label as a whole number, and checked by the record.
        $numbers = $fields->value('numbers') ?? [];

        if (!\is_array($numbers)) {
            throw InvalidDocument::at($fields->at('numbers'), 'a mapping of labels to numbers or tables is expected.');
        }

        return $fields->record(static fn (): Difficulty => new Difficulty(
            $fields->string('key'),
            $fields->texts('names', $locale),
            $source,
            $fields->texts('descriptions', $locale),
            $fields->string('method', required: false),
            $bands,
            $numbers,
        ));
    }

    /**
     * The names and descriptions of a localisation's records of one kind, by key.
     *
     * @return array<string, array{name?: string, description?: string}>
     *
     * @throws InvalidDocument
     */
    private function namedTexts(Fields $fields, string $kind): array
    {
        $records = [];

        foreach ($fields->map($kind)->names() as $key) {
            $texts = $fields->map($kind)->map($key)->only(['name', 'description']);
            $records[$key] = array_filter(['name' => $texts->string('name', false), 'description' => $texts->string('description', false)], is_string(...));
        }

        return $records;
    }

    /**
     * @return array<string, list<Provenance>>
     *
     * @throws InvalidDocument
     */
    private function translationSources(Fields $fields): array
    {
        $translations = $fields->map('translations');
        $sources = [];

        foreach ($translations->names() as $locale) {
            foreach ($translations->list($locale, 64) as $index => $source) {
                $sources[$locale][] = $this->source(Fields::of($source, \sprintf('%s[%d]', $translations->at($locale), $index)));
            }
        }

        return $sources;
    }
}
