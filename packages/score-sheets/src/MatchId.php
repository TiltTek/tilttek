<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * The opaque identifier of a match, the key of its sheet in the store.
 */
final readonly class MatchId
{
    public string $value;

    /**
     * @throws InvalidEntry when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::id($value, 'A match identifier');
    }
}
