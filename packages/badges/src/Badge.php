<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Tilttek\Badges\Criterion\Criterion;
use Tilttek\Badges\Exception\InvalidBadge;

/**
 * A recognition a member earns: a code, a name and a description — texts or translation keys, the application's —,
 * an image, and the criterion that awards it.
 *
 * A badge is earned once, unless it is repeatable: each time its target is reached again is another award. It is
 * withdrawn when the activity that earned it is cancelled, if it is revocable. A hidden badge is shown, and its
 * criterion with it, only once earned. A tier links it to the other badges of a series.
 */
final readonly class Badge
{
    /**
     * The path of the root of a badge's criterion; the paths of the criteria under a combination follow it, `r.0`,
     * `r.1.2`.
     */
    public const string ROOT = 'r';

    public const int MAX_TEXT_LENGTH = 255;

    public function __construct(
        public string $code,
        public string $name,
        public Criterion $criterion,
        public string $description = '',
        public ?string $image = null,
        public bool $repeatable = false,
        public bool $hidden = false,
        public bool $revocable = false,
        public ?Tier $tier = null,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidBadge::because(\sprintf('"%s" is not a badge code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $code));
        }

        if ('' === trim($name) || mb_strlen($name) > self::MAX_TEXT_LENGTH) {
            throw InvalidBadge::because(\sprintf('The name of the badge "%s" is 1 to %d characters.', $code, self::MAX_TEXT_LENGTH));
        }

        if (mb_strlen($description) > 4 * self::MAX_TEXT_LENGTH) {
            throw InvalidBadge::because(\sprintf('The description of the badge "%s" is at most %d characters.', $code, 4 * self::MAX_TEXT_LENGTH));
        }
    }
}
