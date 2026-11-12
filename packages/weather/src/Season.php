<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Tilttek\Weather\Exception\InvalidClimate;

/**
 * A season — spring, the wet season — with its labels. The zones say which seasons they have and what each brings; the
 * calendar of the game says which one it is.
 */
final readonly class Season
{
    /**
     * @var array<string, string> by language
     */
    public array $labels;

    /**
     * @param array<array-key, mixed> $labels by language
     *
     * @throws InvalidClimate
     */
    public function __construct(public string $id, array $labels)
    {
        $this->labels = Rules::labels('seasons.'.Rules::identifier('seasons', $id).'.labels', $labels);
    }
}
