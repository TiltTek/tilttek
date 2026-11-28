<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * A rule variant a system offers — an optional rule a table may choose to play with —, with its names, its
 * descriptions and its source.
 */
final readonly class RuleVariant
{
    /**
     * @param string $key the variant's key in its system, `encumbrance`
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public Translations $descriptions = new Translations(),
    ) {
        Text::key($key, 'a rule variant');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The rule variant "%s" has no name.', $key));
        }
    }
}
