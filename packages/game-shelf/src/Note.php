<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Internal\Identifier;
use Tilttek\GameShelf\Internal\Text;

/**
 * What a member keeps of a game for themselves, apart from the catalogue's sheet: their rating, their comment, their
 * tags and how many times they played it. A note is private to its member.
 */
final readonly class Note
{
    public const int MAX_COMMENT = 2_000;

    public const int MAX_TAGS = 20;

    public const int MAX_TAG = 40;

    public const int MAX_PLAYS = 1_000_000;

    public ?string $comment;

    /** @var list<string> in the member's order */
    public array $tags;

    /**
     * @param ?int         $rating 1 to 10
     * @param list<string> $tags   at most 20, each once, of 1 to 40 characters without ";"
     * @param int          $plays  0 to 1 000 000
     *
     * @throws InvalidShelf when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $game,
        public ?int $rating = null,
        ?string $comment = null,
        array $tags = [],
        public int $plays = 0,
    ) {
        Identifier::game($game);

        if (null !== $rating && ($rating < 1 || $rating > 10)) {
            throw InvalidShelf::because('A rating is 1 to 10.');
        }

        if ($plays < 0 || $plays > self::MAX_PLAYS) {
            throw InvalidShelf::because(\sprintf('A game is played 0 to %d times.', self::MAX_PLAYS));
        }

        $this->comment = null === $comment ? null : Text::lines($comment, self::MAX_COMMENT, 'comment of a note');
        $this->tags = $this->tags($tags);
    }

    /**
     * Whether the note keeps nothing: the shelf drops an empty note.
     */
    public function isEmpty(): bool
    {
        return null === $this->rating && null === $this->comment && [] === $this->tags && 0 === $this->plays;
    }

    public function withPlays(int $plays): self
    {
        return new self($this->game, $this->rating, $this->comment, $this->tags, $plays);
    }

    /**
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private function tags(array $tags): array
    {
        if (\count($tags) > self::MAX_TAGS) {
            throw InvalidShelf::because(\sprintf('A note holds at most %d tags.', self::MAX_TAGS));
        }

        $kept = [];

        foreach ($tags as $tag) {
            $tag = Text::line($tag, self::MAX_TAG, 'tag');

            if (str_contains($tag, ';')) {
                throw InvalidShelf::because('A tag holds no ";".');
            }

            if (\in_array($tag, $kept, true)) {
                throw InvalidShelf::because(\sprintf('The tag "%s" is there twice.', $tag));
            }

            $kept[] = $tag;
        }

        return $kept;
    }
}
