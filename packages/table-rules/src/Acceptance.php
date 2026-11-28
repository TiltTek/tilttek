<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

use DateTimeImmutable;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Internal\Identifier;

/**
 * The last decision of a player on a table's rules: the version answered, accepted or declined, and when.
 */
final readonly class Acceptance
{
    /**
     * @throws InvalidRules when the player is not an identifier or the version is not one
     */
    public function __construct(public string $player, public int $version, public Decision $decision, public DateTimeImmutable $decidedAt)
    {
        Identifier::check($player, 'a player');
        if ($version < 1 || $version > RulebookVersion::MAX_NUMBER) {
            throw InvalidRules::because(\sprintf('The number of a version is 1 to %d.', RulebookVersion::MAX_NUMBER));
        }
    }
}
