<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * A place the characters may go: a name, what the game master describes there, and the identifier of its entry
 * elsewhere — a map, a campaign wiki — when it has one.
 */
final readonly class Place implements Part
{
    public const int MAX_NAME = 120;

    public const int MAX_DESCRIPTION = 2_000;

    public string $name;

    public string $description;

    /**
     * @param string  $name        one line, 1 to 120 characters
     * @param string  $description lines, at most 2000 characters
     * @param ?string $reference   the identifier of its entry elsewhere, opaque to the library
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $name, string $description = '', public ?string $reference = null)
    {
        Identifier::key($key, 'a place');
        $this->name = Text::line($name, self::MAX_NAME, 'name of a place');
        $this->description = Text::block($description, self::MAX_DESCRIPTION, 'description of a place');

        if (null !== $reference) {
            Identifier::check($reference, 'an entry');
        }
    }
}
