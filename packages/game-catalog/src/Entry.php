<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Identifier;

/**
 * A game of the catalogue. It keeps the details its source sent, or that were entered by hand, and beside them the
 * fields the administration corrected: the source sending the entry again changes the first and never the second.
 * It keeps at most one identifier of each open database, and its errata in the order they were published.
 */
final readonly class Entry
{
    public const int MAX_ERRATA = 500;

    /**
     * @param list<ExternalId> $identifiers at most one of each scheme
     * @param ?Details         $corrections the corrected values, read for the fields of `$corrected` only
     * @param list<Field>      $corrected   the fields corrected, in the order of `Field`
     * @param list<Erratum>    $errata      in the order they were published, at most 500
     * @param int              $version     0 for an entry not kept yet
     *
     * @throws InvalidEntry when a value is out of its shape, or the corrections and their fields do not go together
     */
    public function __construct(
        public string $id,
        public Details $source,
        public Origin $origin,
        public array $identifiers = [],
        public ?Details $corrections = null,
        public array $corrected = [],
        public array $errata = [],
        public bool $hidden = false,
        public int $version = 0,
    ) {
        Identifier::check($id, 'an entry');

        if ((!$corrections instanceof Details) !== ([] === $corrected)) {
            throw InvalidEntry::because('An entry has corrected values exactly when it has corrected fields.');
        }

        $schemes = array_map(static fn (ExternalId $identifier): string => $identifier->scheme->value, $identifiers);

        if (\count(array_unique($schemes)) !== \count($schemes)) {
            throw InvalidEntry::because('An entry has at most one identifier of each database.');
        }

        if (\count($errata) > self::MAX_ERRATA) {
            throw InvalidEntry::because(\sprintf('An entry keeps at most %d errata.', self::MAX_ERRATA));
        }
    }

    /**
     * The details to show: those of the source, with the corrected fields in place.
     */
    public function details(): Details
    {
        return $this->corrections instanceof Details ? $this->source->merge($this->corrections, $this->corrected) : $this->source;
    }

    /**
     * The identifier of the entry in a database, if it has one.
     */
    public function identifier(IdentifierScheme $scheme): ?ExternalId
    {
        return array_find($this->identifiers, static fn (ExternalId $identifier): bool => $identifier->scheme === $scheme);
    }

    /**
     * The entry with new details from its source; the corrected fields stay.
     */
    public function withSource(Details $source): self
    {
        return $this->copy(source: $source);
    }

    /**
     * The entry with the given fields corrected to the values of the details.
     *
     * @param list<Field> $fields
     */
    public function withCorrection(Details $values, array $fields): self
    {
        $corrected = array_values(array_filter(
            Field::cases(),
            fn (Field $field): bool => \in_array($field, $fields, true) || \in_array($field, $this->corrected, true),
        ));

        return $this->copy(corrections: ($this->corrections ?? $this->source)->merge($values, $fields), corrected: $corrected);
    }

    /**
     * The entry with the field back to the value of its source.
     */
    public function withoutCorrection(Field $field): self
    {
        $corrected = array_values(array_filter($this->corrected, static fn (Field $kept): bool => $kept !== $field));

        return $this->copy(corrections: [] === $corrected ? null : $this->corrections, corrected: $corrected, keepCorrections: false);
    }

    /**
     * @throws CatalogRefused when the entry already has an identifier of the database, another than this one
     */
    public function withIdentifier(ExternalId $identifier): self
    {
        $held = $this->identifier($identifier->scheme);

        if ($held instanceof ExternalId) {
            if ($held->equals($identifier)) {
                return $this;
            }

            throw CatalogRefused::because(\sprintf('The entry %s already has the identifier %s.', $this->id, $held->key()));
        }

        return $this->copy(identifiers: [...$this->identifiers, $identifier]);
    }

    /**
     * @throws CatalogRefused when the entry has an erratum of that identifier, or has 500
     */
    public function withErratum(Erratum $erratum): self
    {
        if ($this->erratum($erratum->id) instanceof Erratum) {
            throw CatalogRefused::because(\sprintf('The entry %s already has the erratum %s.', $this->id, $erratum->id));
        }

        if (\count($this->errata) >= self::MAX_ERRATA) {
            throw CatalogRefused::because(\sprintf('The entry %s keeps at most %d errata.', $this->id, self::MAX_ERRATA));
        }

        return $this->copy(errata: [...$this->errata, $erratum]);
    }

    public function erratum(string $id): ?Erratum
    {
        return array_find($this->errata, static fn (Erratum $erratum): bool => $erratum->id === $id);
    }

    public function withHidden(bool $hidden): self
    {
        return $this->copy(hidden: $hidden);
    }

    public function withVersion(int $version): self
    {
        return $this->copy(version: $version);
    }

    /**
     * @param ?list<ExternalId> $identifiers
     * @param ?list<Field>      $corrected
     * @param ?list<Erratum>    $errata
     */
    private function copy(
        ?Details $source = null,
        ?array $identifiers = null,
        ?Details $corrections = null,
        ?array $corrected = null,
        ?array $errata = null,
        ?bool $hidden = null,
        ?int $version = null,
        bool $keepCorrections = true,
    ): self {
        return new self(
            $this->id,
            $source ?? $this->source,
            $this->origin,
            $identifiers ?? $this->identifiers,
            $keepCorrections ? $corrections ?? $this->corrections : $corrections,
            $corrected ?? $this->corrected,
            $errata ?? $this->errata,
            $hidden ?? $this->hidden,
            $version ?? $this->version,
        );
    }
}
