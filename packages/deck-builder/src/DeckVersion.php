<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * A saved version of a deck: its number, from 1, the deck as it was, the moment it was saved in UTC, and an optional
 * note — "after the regional", "sideboard tuned".
 */
final readonly class DeckVersion
{
    /**
     * The longest note, in characters.
     */
    public const int NOTE_LENGTH = 500;

    public int $version;

    public DateTimeImmutable $savedAt;

    public ?string $note;

    /**
     * @throws InvalidDeck when the version or the note is refused
     */
    public function __construct(public Deck $deck, int $version, DateTimeInterface $savedAt, ?string $note = null)
    {
        if ($version < 1) {
            throw InvalidDeck::because(\sprintf('A version of a deck is 1 or more; %d is not.', $version));
        }

        if (null !== $note && 1 !== preg_match('/^[^\p{Cc}]{0,'.self::NOTE_LENGTH.'}$/Du', $note)) {
            throw InvalidDeck::because(\sprintf('The note of a version is text of at most %d characters, without control characters.', self::NOTE_LENGTH));
        }

        $this->version = $version;
        $this->savedAt = DateTimeImmutable::createFromInterface($savedAt)->setTimezone(new DateTimeZone('UTC'));
        $this->note = $note;
    }

    /**
     * The reference of this version, which a copy links to.
     */
    public function reference(): DeckReference
    {
        return new DeckReference($this->deck->id, $this->version);
    }
}
