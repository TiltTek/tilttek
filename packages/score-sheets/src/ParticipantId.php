<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * The opaque identifier of a player — or of anything that scores, a pair, a table — on a sheet; the application maps
 * it to its own accounts, never a name.
 */
final readonly class ParticipantId
{
    public string $value;

    /**
     * @throws InvalidEntry when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::id($value, 'A participant identifier');
    }
}
