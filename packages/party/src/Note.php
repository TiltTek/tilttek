<?php

declare(strict_types=1);

namespace Tilttek\Party;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A note the party shares — a lead, a name to remember, a plan —, under a key the application gives, with its text,
 * the member who wrote it, and when it was written and last edited. The author is null once that member is erased; the
 * text stays with the party.
 *
 * The text is 1 to 5000 characters of UTF-8, not blank, with line breaks and tabs the only control characters.
 */
final readonly class Note
{
    public const int MAX_LENGTH = 5000;

    public function __construct(
        public ResourceReference $party,
        public string $key,
        public string $text,
        public ?ResourceReference $author,
        public DateTimeImmutable $writtenAt,
        public ?DateTimeImmutable $editedAt = null,
    ) {
        Text::key($key, 'a note');
        Text::body($text, 'A note', self::MAX_LENGTH);
    }

    public function edited(string $text, DateTimeImmutable $at): self
    {
        return new self($this->party, $this->key, $text, $this->author, $this->writtenAt, $at);
    }

    public function withoutAuthor(): self
    {
        return new self($this->party, $this->key, $this->text, null, $this->writtenAt, $this->editedAt);
    }
}
