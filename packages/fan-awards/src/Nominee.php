<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

use Phil\Resource\ResourceReference;
use Tilttek\FanAwards\Exception\InvalidAward;

/**
 * A work, a person or a group put forward in a category: its key, the option of the ballots — 1 to 64 ASCII letters,
 * digits, "_" or "-", never `no-award`, which stands for "no award" —, its title — 1 to 200 characters of UTF-8 without
 * control characters nor surrounding spaces —, and the application's reference of what it names, if it has one.
 */
final readonly class Nominee
{
    /**
     * The option of a final ballot that stands for "no award".
     */
    public const string NO_AWARD = 'no-award';

    public const int MAX_TITLE_LENGTH = 200;

    public function __construct(
        public string $key,
        public string $title,
        public ?ResourceReference $reference = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $key) || self::NO_AWARD === strtolower($key)) {
            throw InvalidAward::because(\sprintf('A nominee key is 1 to 64 ASCII letters, digits, "_" or "-", and not "%s"; "%s" is not.', self::NO_AWARD, $key));
        }

        if (1 !== preg_match('/^\P{Cc}{1,200}\z/u', $title) || trim($title) !== $title) {
            throw InvalidAward::because(\sprintf('A nominee title is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', self::MAX_TITLE_LENGTH));
        }
    }
}
