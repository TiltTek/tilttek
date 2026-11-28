<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * A role a system declares for the members of a party — healing, defence, scouting —, with its names, its
 * descriptions and its source.
 */
final readonly class PartyRole
{
    /**
     * @param string $key the role's key in its system, `healer`
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public Translations $descriptions = new Translations(),
    ) {
        Text::key($key, 'a party role');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The party role "%s" has no name.', $key));
        }
    }
}
