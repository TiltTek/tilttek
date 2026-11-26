<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use Tilttek\Guilds\Exception\InvalidGuild;

/**
 * What the application keeps on a guild beside its name — a motto, a banner's identifier, a language —: at most 20
 * entries, each named by 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter, and holding a
 * text of at most 500 bytes of valid UTF-8 without control characters. The library reads none of it.
 */
final readonly class Metadata
{
    public const int MAX_ENTRIES = 20;

    public const int MAX_VALUE_LENGTH = 500;

    /**
     * @var array<string, string> the entries, sorted by name
     */
    public array $entries;

    /**
     * @param array<string, string> $entries
     */
    public function __construct(array $entries = [])
    {
        if (\count($entries) > self::MAX_ENTRIES) {
            throw InvalidGuild::because(\sprintf('A guild holds at most %d metadata entries, not %d.', self::MAX_ENTRIES, \count($entries)));
        }

        foreach ($entries as $name => $value) {
            if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
                throw InvalidGuild::because(\sprintf('A metadata entry is named by 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $name));
            }

            if (\strlen($value) > self::MAX_VALUE_LENGTH || 1 !== preg_match('/^\P{Cc}*$/Du', $value)) {
                throw InvalidGuild::because(\sprintf('The metadata entry "%s" holds at most %d bytes of UTF-8 without control characters.', $name, self::MAX_VALUE_LENGTH));
            }
        }

        ksort($entries, \SORT_STRING);
        $this->entries = $entries;
    }

    public function get(string $name): ?string
    {
        return $this->entries[$name] ?? null;
    }
}
