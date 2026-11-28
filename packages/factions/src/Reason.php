<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;

/**
 * Why a standing, a rank or a relation changed, in the game master's words, and the session it happened in when one
 * is named.
 */
final readonly class Reason
{
    /** The longest reason, in characters. */
    public const int MAX_TEXT = 500;

    public string $text;

    /**
     * @param string      $text    one line of 1 to `MAX_TEXT` characters
     * @param string|null $session the identifier of the session, or null
     *
     * @throws InvalidRoster when the text or the identifier is out of its shape or its bounds
     */
    public function __construct(string $text, public ?string $session = null)
    {
        $this->text = Text::line($text, self::MAX_TEXT, 'reason of a change');

        if (null !== $session) {
            Identifier::check($session, 'a session');
        }
    }
}
