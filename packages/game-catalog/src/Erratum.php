<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use DateTimeImmutable;
use Tilttek\GameCatalog\Internal\Identifier;
use Tilttek\GameCatalog\Internal\Text;

/**
 * A correction the author or the publisher made to the game: where it applies, what it says, and when it was published.
 * It is shown on the entry, and its publication is the moment to tell the members who follow the game.
 */
final readonly class Erratum
{
    public const int MAX_LOCATION = 120;

    public const int MAX_TEXT = 4_000;

    public string $location;

    public string $text;

    /**
     * @param string $location where it applies: a page, a card, a rule
     *
     * @throws Exception\InvalidEntry when the identifier, the location or the text is out of its shape or its bounds
     */
    public function __construct(public string $id, string $location, string $text, public DateTimeImmutable $publishedAt)
    {
        Identifier::check($id, 'an erratum');
        $this->location = Text::line($location, self::MAX_LOCATION, 'location of an erratum');
        $this->text = Text::lines($text, self::MAX_TEXT, 'text of an erratum');
    }
}
