<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * An attribute of a system's characters — Strength, Agility —, with its names, its abbreviations, its bounds when the
 * system has some, and its source.
 */
final readonly class Attribute
{
    /**
     * @param string       $key           the attribute's key in its system, `str`
     * @param Translations $names         its names, at least one
     * @param Translations $abbreviations its abbreviations, `STR`, `FOR`
     * @param ?int         $minimum       the lowest value the system allows, when it has one
     * @param ?int         $maximum       the highest value the system allows, when it has one
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public Translations $abbreviations = new Translations(),
        public Translations $descriptions = new Translations(),
        public ?int $minimum = null,
        public ?int $maximum = null,
    ) {
        Text::key($key, 'an attribute');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The attribute "%s" has no name.', $key));
        }

        if (null !== $minimum && null !== $maximum && $minimum > $maximum) {
            throw InvalidRecord::because(\sprintf('The attribute "%s" has a minimum, %d, above its maximum, %d.', $key, $minimum, $maximum));
        }
    }

    /**
     * Whether a value is within the attribute's bounds; any value is, for an attribute without bounds.
     */
    public function allows(int $value): bool
    {
        return (null === $this->minimum || $value >= $this->minimum) && (null === $this->maximum || $value <= $this->maximum);
    }
}
