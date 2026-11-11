<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use Tilttek\Activity\Exception\InvalidActivity;

/**
 * A few facts about a gesture that the rules may read — the game system of a game played, the level of a quest, a
 * flag —, never a text the member wrote: a comment is referenced as the activity's object, not copied here. At most
 * 16 entries, named like `system` or `first_time`, each an integer, a boolean or a string of at most 100 bytes;
 * entries are kept sorted by name.
 */
final readonly class Context
{
    public const int MAX_ENTRIES = 16;

    public const int MAX_STRING_LENGTH = 100;

    /**
     * @var array<string, bool|int|string>
     */
    public array $values;

    /**
     * @param array<string, bool|int|string> $values
     */
    public function __construct(array $values = [])
    {
        if (\count($values) > self::MAX_ENTRIES) {
            throw InvalidActivity::because(\sprintf('A context has at most %d entries; this one has %d.', self::MAX_ENTRIES, \count($values)));
        }

        foreach ($values as $name => $value) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
                throw InvalidActivity::because(\sprintf('"%s" is not a context entry name: 1 to 64 lower-case letters, digits and "_", starting with a letter.', $name));
            }

            if (\is_string($value) && \strlen($value) > self::MAX_STRING_LENGTH) {
                throw InvalidActivity::because(\sprintf('The context entry "%s" is at most %d bytes; it is %d.', $name, self::MAX_STRING_LENGTH, \strlen($value)));
            }
        }

        ksort($values, \SORT_STRING);
        $this->values = $values;
    }

    public function get(string $name): bool|int|string|null
    {
        return $this->values[$name] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->values === $other->values;
    }
}
