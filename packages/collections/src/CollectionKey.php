<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The key a copy is given under, from what makes it unique — `order:2026-0193:line:2`, `quest:first-steps:member:44`
 * —: the same key given twice gives one copy. The application's keys are 1 to 200 bytes, without whitespace, control
 * characters or `#`, which only the keys the library derives carry — the copies of a pack, of a set given whole, of
 * the item of the day.
 */
final readonly class CollectionKey
{
    public const int MAX_LENGTH = 200;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidCollection::because(\sprintf('A collection key is 1 to %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F#]/', $value)) {
            throw InvalidCollection::because(\sprintf('A collection key cannot contain whitespace, control characters or "#"; "%s" does.', $value));
        }

        return new self($value);
    }

    /**
     * A key the library derives from this one: the key, `#`, and what tells the copies under it apart.
     */
    public function derive(string $suffix): self
    {
        return new self($this->value.'#'.$suffix);
    }

    /**
     * A key the library makes of its own, starting with `#`, which no application key does.
     */
    public static function internal(string $value): self
    {
        return new self('#'.$value);
    }
}
