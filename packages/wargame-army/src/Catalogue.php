<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Constraint\PointsShare;
use Tilttek\WargameArmy\Constraint\UnitCount;
use Tilttek\WargameArmy\Contract\DeclaredConstraint;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * The data of a game, apart from the engine: its units with their options and points, its detachment types, the
 * number of detachments an army takes, and the constraints it declares. The application provides its catalogues —
 * its own, invented, or under a licence it holds —; the library ships none.
 */
final readonly class Catalogue
{
    /**
     * The most points of a unit, a model or an option.
     */
    public const int MAX_POINTS = 100_000;

    /**
     * The most models of a unit, and the most times an option is taken.
     */
    public const int MAX_COUNT = 1_000;

    /**
     * The most units of a catalogue.
     */
    public const int MAX_UNITS = 2_000;

    /**
     * The most detachment types of a catalogue.
     */
    public const int MAX_DETACHMENT_TYPES = 50;

    /**
     * The most constraints of a catalogue.
     */
    public const int MAX_CONSTRAINTS = 200;

    public string $code;

    public string $name;

    public string $version;

    /** @var array<string, UnitEntry> */
    public array $units;

    /** @var array<string, DetachmentType> */
    public array $detachmentTypes;

    public int $minDetachments;

    public int $maxDetachments;

    /** @var list<DeclaredConstraint> */
    public array $constraints;

    /**
     * @param list<UnitEntry>          $units           1 to 2 000 units, each code once
     * @param list<DetachmentType>     $detachmentTypes 1 to 50 detachment types, each code once
     * @param list<DeclaredConstraint> $constraints     up to 200 constraints, each unit they name in the catalogue
     *
     * @throws InvalidCatalogue when a value is refused
     */
    public function __construct(
        string $code,
        string $name,
        string $version,
        array $units,
        array $detachmentTypes,
        int $minDetachments = 1,
        int $maxDetachments = 1,
        array $constraints = [],
    ) {
        $this->code = Identifiers::code($code, 'catalogue');
        $this->name = Identifiers::name($name, 'catalogue');
        $this->version = Identifiers::version($version);
        $this->minDetachments = Identifiers::bounded($minDetachments, 0, ArmyList::MAX_DETACHMENTS, 'the fewest detachments of an army');
        $this->maxDetachments = Identifiers::bounded($maxDetachments, max(1, $minDetachments), ArmyList::MAX_DETACHMENTS, 'the most detachments of an army');
        $this->units = $this->byCode($units, 'units', self::MAX_UNITS);
        $this->detachmentTypes = $this->byCode($detachmentTypes, 'detachment types', self::MAX_DETACHMENT_TYPES);

        if (\count($constraints) > self::MAX_CONSTRAINTS) {
            throw InvalidCatalogue::because(\sprintf('A catalogue has at most %d constraints.', self::MAX_CONSTRAINTS));
        }

        foreach ($constraints as $constraint) {
            $unit = $constraint->selector->unit;

            if (null !== $unit && !isset($this->units[$unit])) {
                throw InvalidCatalogue::because(\sprintf('A constraint of the catalogue "%s" names the unit "%s", which the catalogue does not hold.', $code, $unit));
            }
        }

        $this->constraints = $constraints;
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a catalogue
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'catalogue', ['code', 'name', 'version', 'units', 'detachment_types'], ['min_detachments', 'max_detachments', 'constraints']);

        return new self(
            $input->string($object, 'code', 'catalogue'),
            $input->string($object, 'name', 'catalogue'),
            $input->string($object, 'version', 'catalogue'),
            array_map(UnitEntry::fromArray(...), $input->list($object, 'units', 'catalogue', self::MAX_UNITS)),
            array_map(DetachmentType::fromArray(...), $input->list($object, 'detachment_types', 'catalogue', self::MAX_DETACHMENT_TYPES)),
            $input->int($object, 'min_detachments', 'catalogue', 1),
            $input->int($object, 'max_detachments', 'catalogue', 1),
            array_map(self::constraint(...), $input->list($object, 'constraints', 'catalogue', self::MAX_CONSTRAINTS)),
        );
    }

    /**
     * @throws InvalidCatalogue when the text is not JSON or does not read as a catalogue
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(new ArrayInput(Fault::Catalogue)->json($json, 'catalogue'));
    }

    public function unit(string $code): ?UnitEntry
    {
        return $this->units[$code] ?? null;
    }

    public function detachmentType(string $code): ?DetachmentType
    {
        return $this->detachmentTypes[$code] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'units' => array_values(array_map(static fn (UnitEntry $unit): array => $unit->toArray(), $this->units)),
            'detachment_types' => array_values(array_map(static fn (DetachmentType $type): array => $type->toArray(), $this->detachmentTypes)),
            'min_detachments' => $this->minDetachments,
            'max_detachments' => $this->maxDetachments,
            'constraints' => array_map(static fn (DeclaredConstraint $constraint): array => $constraint->toArray(), $this->constraints),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @throws InvalidCatalogue when the array does not read as a constraint of the library
     */
    private static function constraint(mixed $data): DeclaredConstraint
    {
        return match (\is_array($data) ? $data['type'] ?? null : null) {
            'count' => UnitCount::fromArray($data),
            'points-share' => PointsShare::fromArray($data),
            default => throw InvalidCatalogue::because('A constraint is an object whose "type" is "count" or "points-share".'),
        };
    }

    /**
     * @template T of UnitEntry|DetachmentType
     *
     * @param list<T> $entries
     *
     * @return array<string, T>
     *
     * @throws InvalidCatalogue when the list is empty, too long, or holds a code twice
     */
    private function byCode(array $entries, string $what, int $max): array
    {
        if ([] === $entries || \count($entries) > $max) {
            throw InvalidCatalogue::because(\sprintf('A catalogue has 1 to %d %s.', $max, $what));
        }

        $byCode = [];

        foreach ($entries as $entry) {
            if (isset($byCode[$entry->code])) {
                throw InvalidCatalogue::because(\sprintf('A catalogue has two %s "%s".', $what, $entry->code));
            }

            $byCode[$entry->code] = $entry;
        }

        return $byCode;
    }
}
