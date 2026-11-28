<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * A term of a system — a condition, a skill, a damage type, a trait —, with its names, its descriptions, its category
 * and its source.
 */
final readonly class Term
{
    /**
     * @param string  $key      the term's key in its system, `condition.blinded`
     * @param ?string $category the kind of term, a key itself: `condition`, `skill`
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public ?string $category = null,
        public Translations $descriptions = new Translations(),
    ) {
        Text::key($key, 'a term');

        if (null !== $category) {
            Text::key($category, 'the category of a term');
        }

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The term "%s" has no name.', $key));
        }
    }
}
