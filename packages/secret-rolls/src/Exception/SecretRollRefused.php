<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Exception;

use DomainException;

/**
 * A step the journal of a scene does not allow: a roll in a closed or full scene, the reveal of a roll the scene never
 * committed, revealed already or no longer sealed, or the closing of a scene closed already.
 */
final class SecretRollRefused extends DomainException implements SecretRollsException
{
    public static function sceneClosed(string $scene): self
    {
        return new self(\sprintf('The scene %s is closed: it takes no more rolls.', $scene));
    }

    public static function sceneFull(string $scene, int $entries): self
    {
        return new self(\sprintf('The journal of the scene %s holds %d entries, the most it takes.', $scene, $entries));
    }

    public static function unknownRoll(string $scene, string $roll): self
    {
        return new self(\sprintf('The scene %s committed no roll %s.', $scene, $roll));
    }

    public static function alreadyRevealed(string $roll): self
    {
        return new self(\sprintf('The roll %s was revealed already; a roll is revealed once.', $roll));
    }

    public static function notSealed(string $roll): self
    {
        return new self(\sprintf('The store keeps no sealed roll %s: its commitment can no longer be opened.', $roll));
    }
}
