<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * A free note of the game master: a reminder, a rule to look up, an idea for later.
 */
final readonly class Note implements Part
{
    public const int MAX_TEXT = 4_000;

    public string $text;

    /**
     * @param string $text lines, 1 to 4000 characters
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $text)
    {
        Identifier::key($key, 'a note');
        $this->text = Text::block($text, self::MAX_TEXT, 'text of a note', true);
    }
}
