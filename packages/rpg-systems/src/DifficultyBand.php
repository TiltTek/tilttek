<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * One band of a system's difficulty numbers — `easy`, `hard`, `severe` —, with its names and, when the band stands for
 * a single number, that number: the class of a check, or what the band adds to a number found elsewhere.
 */
final readonly class DifficultyBand
{
    /**
     * @param string $key    the band's key, `very-hard`
     * @param ?int   $number the band's own number, within ±`Difficulty::MAX_NUMBER`; null when the band has none
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public ?int $number = null,
    ) {
        Text::key($key, 'a band of difficulty');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The band of difficulty "%s" has no name.', $key));
        }

        if (null !== $number && abs($number) > Difficulty::MAX_NUMBER) {
            throw InvalidRecord::because(\sprintf('The number of the band of difficulty "%s" lies within ±%d.', $key, Difficulty::MAX_NUMBER));
        }
    }
}
