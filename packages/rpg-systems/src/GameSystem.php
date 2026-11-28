<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;

/**
 * A game system as other tools read it: its names and version, its attributes, the dice expressions it names, its
 * terms, the rule variants it offers, its difficulty numbers, the roles it declares for a party, and the source of each
 * record and of each translation.
 */
final readonly class GameSystem
{
    /**
     * The most records — attributes, dice expressions, terms, rule variants, difficulty numbers and party roles
     * together — one system holds.
     */
    public const int MAX_RECORDS = 10_000;

    /** @var array<string, Attribute> */
    private array $attributes;

    /** @var array<string, DiceExpression> */
    private array $dice;

    /** @var array<string, Term> */
    private array $terms;

    /** @var array<string, RuleVariant> */
    private array $variants;

    /** @var array<string, Difficulty> */
    private array $difficulties;

    /** @var array<string, PartyRole> */
    private array $partyRoles;

    /** @var array<string, list<Provenance>> the sources of the translations, by normalised locale */
    public array $translationSources;

    /**
     * @param string                          $key                the system's key, `srd-5.2`
     * @param ?string                         $version            the version of the system the records describe
     * @param list<Attribute>                 $attributes         in the order the system lists them
     * @param list<DiceExpression>            $dice               in the order the system lists them
     * @param list<Term>                      $terms              in the order the system lists them
     * @param array<string, list<Provenance>> $translationSources the sources of the translations, by locale
     * @param list<RuleVariant>               $variants           in the order the system lists them
     * @param list<Difficulty>                $difficulties       in the order the system lists them
     * @param list<PartyRole>                 $partyRoles         in the order the system lists them
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public ?string $version = null,
        array $attributes = [],
        array $dice = [],
        array $terms = [],
        array $translationSources = [],
        array $variants = [],
        array $difficulties = [],
        array $partyRoles = [],
    ) {
        Text::key($key, 'a system');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The system "%s" has no name.', $key));
        }

        if (null !== $version) {
            Text::check($version, \sprintf('The version of the system "%s"', $key));
        }

        if (\count($attributes) + \count($dice) + \count($terms) + \count($variants) + \count($difficulties) + \count($partyRoles) > self::MAX_RECORDS) {
            throw InvalidRecord::because(\sprintf('The system "%s" holds more than %d records.', $key, self::MAX_RECORDS));
        }

        $this->attributes = $this->byKey($attributes, 'attribute', $key);
        $this->dice = $this->byKey($dice, 'dice expression', $key);
        $this->terms = $this->byKey($terms, 'term', $key);
        $this->variants = $this->byKey($variants, 'rule variant', $key);
        $this->difficulties = $this->byKey($difficulties, 'difficulty numbers', $key);
        $this->partyRoles = $this->byKey($partyRoles, 'party role', $key);
        $sources = [];

        foreach ($translationSources as $locale => $provenances) {
            $sources[Text::locale($locale)] = [...$sources[Text::locale($locale)] ?? [], ...$provenances];
        }

        $this->translationSources = $sources;
    }

    /**
     * The system's name in a locale, as `Translations::in()` finds it.
     */
    public function name(string $locale = 'en'): string
    {
        return (string) $this->names->in($locale);
    }

    /**
     * @throws UnknownRecord
     */
    public function attribute(string $key): Attribute
    {
        return $this->attributes[$key] ?? throw UnknownRecord::of('attribute', $key, $this->key);
    }

    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @return list<Attribute>
     */
    public function attributes(): array
    {
        return array_values($this->attributes);
    }

    /**
     * @throws UnknownRecord
     */
    public function dice(string $key): DiceExpression
    {
        return $this->dice[$key] ?? throw UnknownRecord::of('dice expression', $key, $this->key);
    }

    public function hasDice(string $key): bool
    {
        return isset($this->dice[$key]);
    }

    /**
     * @return list<DiceExpression>
     */
    public function diceExpressions(): array
    {
        return array_values($this->dice);
    }

    /**
     * @throws UnknownRecord
     */
    public function term(string $key): Term
    {
        return $this->terms[$key] ?? throw UnknownRecord::of('term', $key, $this->key);
    }

    public function hasTerm(string $key): bool
    {
        return isset($this->terms[$key]);
    }

    /**
     * @param ?string $category only the terms of this category; every term when null
     *
     * @return list<Term>
     */
    public function terms(?string $category = null): array
    {
        return array_values(null === $category ? $this->terms : array_filter($this->terms, static fn (Term $term): bool => $term->category === $category));
    }

    /**
     * The categories of the terms, in the order they first appear.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->terms as $term) {
            if (null !== $term->category) {
                $categories[$term->category] = $term->category;
            }
        }

        return array_values($categories);
    }

    /**
     * @throws UnknownRecord
     */
    public function variant(string $key): RuleVariant
    {
        return $this->variants[$key] ?? throw UnknownRecord::of('rule variant', $key, $this->key);
    }

    public function hasVariant(string $key): bool
    {
        return isset($this->variants[$key]);
    }

    /**
     * @return list<RuleVariant>
     */
    public function variants(): array
    {
        return array_values($this->variants);
    }

    /**
     * @throws UnknownRecord
     */
    public function difficulty(string $key): Difficulty
    {
        return $this->difficulties[$key] ?? throw UnknownRecord::of('difficulty numbers', $key, $this->key);
    }

    public function hasDifficulty(string $key): bool
    {
        return isset($this->difficulties[$key]);
    }

    /**
     * @return list<Difficulty>
     */
    public function difficulties(): array
    {
        return array_values($this->difficulties);
    }

    /**
     * @throws UnknownRecord
     */
    public function partyRole(string $key): PartyRole
    {
        return $this->partyRoles[$key] ?? throw UnknownRecord::of('party role', $key, $this->key);
    }

    public function hasPartyRole(string $key): bool
    {
        return isset($this->partyRoles[$key]);
    }

    /**
     * @return list<PartyRole>
     */
    public function partyRoles(): array
    {
        return array_values($this->partyRoles);
    }

    /**
     * Every source the system draws from, each once: the system's own, its records', then its translations' — in one
     * locale, or in all of them.
     *
     * @param ?string $locale the locale whose translations are shown; every translation when null
     *
     * @return list<Provenance>
     */
    public function provenances(?string $locale = null): array
    {
        $sources = [$this->provenance];

        foreach ([$this->attributes, $this->dice, $this->terms, $this->variants, $this->difficulties, $this->partyRoles] as $records) {
            foreach ($records as $record) {
                $sources[] = $record->provenance;
            }
        }

        $locale = null === $locale ? null : Text::locale($locale);

        foreach ($this->translationSources as $translated => $translations) {
            if (null === $locale || $translated === $locale || $translated === explode('_', $locale)[0]) {
                array_push($sources, ...$translations);
            }
        }

        $distinct = [];

        foreach ($sources as $source) {
            $distinct[$source->identity()] ??= $source;
        }

        return array_values($distinct);
    }

    /**
     * The system with the texts of a localisation laid over its own, and the source of that translation added.
     *
     * @throws UnknownRecord when the localisation names a record the system does not hold
     * @throws InvalidRecord
     */
    public function localised(Localisation $localisation): self
    {
        $locale = $localisation->locale;
        $attributes = $this->attributes;
        $dice = $this->dice;
        $terms = $this->terms;
        $variants = $this->variants;
        $difficulties = $this->difficulties;
        $partyRoles = $this->partyRoles;

        foreach ($localisation->attributes as $key => $texts) {
            $attribute = $this->attribute($key);
            $attributes[$key] = new Attribute(
                $key,
                $this->with($attribute->names, $locale, $texts['name'] ?? null),
                $attribute->provenance,
                $this->with($attribute->abbreviations, $locale, $texts['abbreviation'] ?? null),
                $this->with($attribute->descriptions, $locale, $texts['description'] ?? null),
                $attribute->minimum,
                $attribute->maximum,
            );
        }

        foreach ($localisation->dice as $key => $name) {
            $expression = $this->dice($key);
            $dice[$key] = new DiceExpression($key, $expression->notation, $expression->names->with($locale, $name), $expression->provenance);
        }

        foreach ($localisation->terms as $key => $texts) {
            $term = $this->term($key);
            $terms[$key] = new Term(
                $key,
                $this->with($term->names, $locale, $texts['name'] ?? null),
                $term->provenance,
                $term->category,
                $this->with($term->descriptions, $locale, $texts['description'] ?? null),
            );
        }

        foreach ($localisation->variants as $key => $texts) {
            $variant = $this->variant($key);
            $variants[$key] = new RuleVariant(
                $key,
                $this->with($variant->names, $locale, $texts['name'] ?? null),
                $variant->provenance,
                $this->with($variant->descriptions, $locale, $texts['description'] ?? null),
            );
        }

        foreach ($localisation->difficulties as $key => $texts) {
            $difficulty = $this->difficulty($key);
            $bandNames = $texts['bands'] ?? [];
            $bands = [];

            foreach ($difficulty->bands as $band) {
                $bands[] = new DifficultyBand($band->key, $this->with($band->names, $locale, $bandNames[$band->key] ?? null), $band->number);
                unset($bandNames[$band->key]);
            }

            if ([] !== $bandNames) {
                throw UnknownRecord::of('band of difficulty', array_key_first($bandNames), $this->key);
            }

            $difficulties[$key] = new Difficulty(
                $key,
                $this->with($difficulty->names, $locale, $texts['name'] ?? null),
                $difficulty->provenance,
                $this->with($difficulty->descriptions, $locale, $texts['description'] ?? null),
                $difficulty->method,
                $bands,
                $difficulty->numbers,
            );
        }

        foreach ($localisation->partyRoles as $key => $texts) {
            $role = $this->partyRole($key);
            $partyRoles[$key] = new PartyRole(
                $key,
                $this->with($role->names, $locale, $texts['name'] ?? null),
                $role->provenance,
                $this->with($role->descriptions, $locale, $texts['description'] ?? null),
            );
        }

        $sources = $this->translationSources;
        $sources[$locale] = [...$sources[$locale] ?? [], $localisation->source];

        return new self(
            $this->key,
            $this->with($this->names, $locale, $localisation->systemName),
            $this->provenance,
            $this->version,
            array_values($attributes),
            array_values($dice),
            array_values($terms),
            $sources,
            array_values($variants),
            array_values($difficulties),
            array_values($partyRoles),
        );
    }

    private function with(Translations $translations, string $locale, ?string $text): Translations
    {
        return null === $text ? $translations : $translations->with($locale, $text);
    }

    /**
     * @template T of Attribute|DiceExpression|Term|RuleVariant|Difficulty|PartyRole
     *
     * @param list<T> $records
     *
     * @return array<string, T>
     */
    private function byKey(array $records, string $kind, string $system): array
    {
        $byKey = [];

        foreach ($records as $record) {
            if (isset($byKey[$record->key])) {
                throw InvalidRecord::because(\sprintf('The system "%s" holds two of the %s "%s".', $system, $kind, $record->key));
            }

            $byKey[$record->key] = $record;
        }

        return $byKey;
    }
}
