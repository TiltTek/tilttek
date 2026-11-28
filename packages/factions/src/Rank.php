<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;

/**
 * A rank a character may hold within a faction, found by its key.
 */
final readonly class Rank
{
    /** The longest title, in characters. */
    public const int MAX_TITLE = 80;

    public string $title;

    /**
     * @param string $key   the rank's key within its faction
     * @param string $title one line of 1 to `MAX_TITLE` characters
     *
     * @throws InvalidRoster when the key or the title is out of its shape or its bounds
     */
    public function __construct(public string $key, string $title)
    {
        Identifier::key($key, 'a rank');
        $this->title = Text::line($title, self::MAX_TITLE, 'title of a rank');
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->title === $other->title;
    }
}
