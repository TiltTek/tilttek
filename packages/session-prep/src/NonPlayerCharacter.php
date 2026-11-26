<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * A character the game master plays in this session: a name, their part in it, notes, and the identifier of their
 * entry elsewhere — a campaign wiki, a bestiary — when they have one.
 */
final readonly class NonPlayerCharacter implements Part
{
    public const int MAX_NAME = 120;

    public const int MAX_ROLE = 200;

    public const int MAX_NOTES = 2_000;

    public string $name;

    public string $role;

    public string $notes;

    /**
     * @param string  $name      one line, 1 to 120 characters
     * @param string  $role      one line, at most 200 characters: what they want, what they do in the session
     * @param string  $notes     lines, at most 2000 characters
     * @param ?string $reference the identifier of their entry elsewhere, opaque to the library
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $name, string $role = '', string $notes = '', public ?string $reference = null)
    {
        Identifier::key($key, 'a character');
        $this->name = Text::line($name, self::MAX_NAME, 'name of a character');
        $this->role = Text::line($role, self::MAX_ROLE, 'role of a character', false);
        $this->notes = Text::block($notes, self::MAX_NOTES, 'notes on a character');

        if (null !== $reference) {
            Identifier::check($reference, 'an entry');
        }
    }
}
