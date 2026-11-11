<?php

declare(strict_types=1);

namespace Tilttek\LootTables;

use Tilttek\LootTables\Exception\InvalidLoot;

/**
 * One entry of a table: its key, its name — what a receipt shows, or the whole text of an entry drawn from a list —,
 * the path or URL of its image, and the slot it fills on what is made of it, such as `head` or `left-hand`.
 */
final readonly class LootEntry
{
    /** The longest name, in characters. */
    public const int MAX_NAME = 1000;

    public const string KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/D';

    /**
     * @param string      $key   1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit
     * @param string      $name  1 to 1000 characters of UTF-8, not blank
     * @param string|null $image 1 to 2048 visible ASCII characters
     * @param string|null $slot  1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit
     *
     * @throws InvalidLoot
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $image = null,
        public ?string $slot = null,
    ) {
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw InvalidLoot::because(\sprintf('"%s" is not the key of an entry: 1 to 64 lowercase letters, digits, "_" or "-".', $key));
        }

        self::checkName($name, \sprintf('The name of the entry %s', $key));

        if (null !== $image && 1 !== preg_match('/^[\x21-\x7E]{1,2048}$/D', $image)) {
            throw InvalidLoot::because(\sprintf('The image of the entry %s is not a path or URL: 1 to 2048 visible ASCII characters.', $key));
        }

        if (null !== $slot && 1 !== preg_match(self::KEY_PATTERN, $slot)) {
            throw InvalidLoot::because(\sprintf('"%s" is not the slot of the entry %s: 1 to 64 lowercase letters, digits, "_" or "-".', $slot, $key));
        }
    }

    /**
     * @throws InvalidLoot when the name is blank, is not UTF-8 or is longer than its limit
     */
    public static function checkName(string $name, string $what, int $limit = self::MAX_NAME): void
    {
        if ('' === trim($name) || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > $limit) {
            throw InvalidLoot::because(\sprintf('%s is 1 to %d characters of UTF-8, not blank.', $what, $limit));
        }
    }
}
