<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Event;

/**
 * A character was rolled: an application sends a message from it — the rules and the system, nothing else — and keeps
 * the character itself where it keeps its sheets. The seed stays out: a caller may have written it.
 */
final readonly class CharacterRolled
{
    /**
     * @param string $rules  the key of the creation rules
     * @param string $system the key of their system
     * @param bool   $seeded whether the caller gave the seed, rather than the library drawing one
     */
    public function __construct(
        public string $rules,
        public string $system,
        public bool $seeded,
    ) {
    }
}
