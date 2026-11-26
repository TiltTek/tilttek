<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * The opaque identifier of a team, whose score the scores of its members compose.
 */
final readonly class TeamId
{
    public string $value;

    /**
     * @throws InvalidEntry when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::id($value, 'A team identifier');
    }
}
