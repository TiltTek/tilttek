<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Contract;

use Tilttek\RandomCharacter\Event\CharacterRolled;

/**
 * Told of each character rolled, by identifiers only.
 */
interface CharacterObserver
{
    public function notify(CharacterRolled $event): void;
}
