<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use Tilttek\Activity\Exception\InvalidActivity;

/**
 * What a member did, named by the application: `news.commented`, `game.played`, `order.paid`. Segments of lower-case
 * letters, digits and underscores, each starting with a letter, joined by dots; at most 128 bytes. The library gives
 * a verb no meaning: the rules of the packages that read the journal do.
 */
final readonly class Verb
{
    public const int MAX_LENGTH = 128;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidActivity::because(\sprintf('A verb is at most %d bytes; "%s" is %d.', self::MAX_LENGTH, $value, \strlen($value)));
        }

        if (1 !== preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $value)) {
            throw InvalidActivity::because(\sprintf('"%s" is not a verb: lower-case segments of letters, digits and "_", each starting with a letter, joined by dots, such as "game.played".', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
