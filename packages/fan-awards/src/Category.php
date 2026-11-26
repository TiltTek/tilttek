<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

use Phil\Poll\Counting\InstantRunoff;
use Tilttek\FanAwards\Exception\InvalidAward;

/**
 * A category of the awards and its rules: how many nominees a nomination ballot holds, how many go on to the
 * semifinal and to the final, the counting method of the final, and whether the final offers "no award".
 *
 * The key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; the name 1 to 100 characters of
 * UTF-8 without control characters nor surrounding spaces. A nomination ballot holds 1 to 10 nominees, 5 by default;
 * the semifinal takes 2 to 20, 10 by default; the final 2 to 20, 5 by default, never more than the semifinal. The
 * method is the name of a ranked counting method of `phil/poll` — `instant_runoff` by default, `borda` or `schulze`.
 */
final readonly class Category
{
    public const int MAX_NOMINATIONS = 10;

    public const int MAX_SHORTLIST = 20;

    public function __construct(
        public string $key,
        public string $name,
        public int $nominations = 5,
        public int $semifinalists = 10,
        public int $finalists = 5,
        public string $method = InstantRunoff::NAME,
        public bool $noAward = true,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidAward::because(\sprintf('A category key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "%s" is not.', $key));
        }

        if (1 !== preg_match('/^\P{Cc}{1,100}\z/u', $name) || trim($name) !== $name) {
            throw InvalidAward::because('A category name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.');
        }

        if ($nominations < 1 || $nominations > self::MAX_NOMINATIONS) {
            throw InvalidAward::because(\sprintf('A nomination ballot holds 1 to %d nominees; not %d.', self::MAX_NOMINATIONS, $nominations));
        }

        if ($semifinalists < 2 || $semifinalists > self::MAX_SHORTLIST) {
            throw InvalidAward::because(\sprintf('A semifinal takes 2 to %d nominees; not %d.', self::MAX_SHORTLIST, $semifinalists));
        }

        if ($finalists < 2 || $finalists > $semifinalists) {
            throw InvalidAward::because(\sprintf('A final takes 2 nominees to the %d of the semifinal; not %d.', $semifinalists, $finalists));
        }
    }
}
