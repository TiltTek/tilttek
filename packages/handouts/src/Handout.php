<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Handouts\Exception\InvalidHandout;

/**
 * A document the game master prepares for the players of a table — a letter found on a body, a map, a wanted poster —
 * under the reference the application gives, with a title, a text, attachments, or a text and attachments. The table
 * is the campaign, the session or the game it belongs to, by the application's reference.
 *
 * Each edit raises the revision, starting at 1, so that a player who saw an earlier revision is told it changed.
 *
 * The title is 1 to 150 characters of UTF-8 without control characters nor surrounding spaces; the text, 1 to 20,000
 * characters, not blank, with line breaks and tabs the only control characters; the attachments, at most 50 distinct
 * files.
 */
final readonly class Handout
{
    public const int MAX_TITLE = 150;

    public const int MAX_TEXT = 20_000;

    public const int MAX_ATTACHMENTS = 50;

    /** @var list<Attachment> */
    public array $attachments;

    /**
     * @param list<Attachment> $attachments
     *
     * @throws InvalidHandout
     */
    public function __construct(
        public ResourceReference $reference,
        public ResourceReference $table,
        public string $title,
        public ?string $text,
        array $attachments,
        public DateTimeImmutable $createdAt,
        public int $revision = 1,
        public ?DateTimeImmutable $editedAt = null,
    ) {
        Text::line($title, 'The title of a handout', self::MAX_TITLE);

        if (null !== $text) {
            Text::body($text, 'The text of a handout', self::MAX_TEXT);
        }

        if (\count($attachments) > self::MAX_ATTACHMENTS) {
            throw InvalidHandout::because(\sprintf('A handout holds at most %d attachments.', self::MAX_ATTACHMENTS));
        }

        $files = [];
        $list = [];

        foreach ($attachments as $attachment) {
            $file = $attachment->file->toString();

            if (isset($files[$file])) {
                throw InvalidHandout::because(\sprintf('A handout shows the file "%s" once.', $file));
            }

            $files[$file] = $file;
            $list[] = $attachment;
        }

        if (null === $text && [] === $list) {
            throw InvalidHandout::because('A handout holds a text, an attachment, or both.');
        }

        if ($revision < 1) {
            throw InvalidHandout::because(\sprintf('The revision of a handout starts at 1; it is %d.', $revision));
        }

        $this->attachments = $list;
    }

    /**
     * The handout with another title, text and attachments, at the next revision.
     *
     * @param list<Attachment> $attachments
     */
    public function edited(string $title, ?string $text, array $attachments, DateTimeImmutable $at): self
    {
        return new self($this->reference, $this->table, $title, $text, $attachments, $this->createdAt, $this->revision + 1, $at);
    }
}
