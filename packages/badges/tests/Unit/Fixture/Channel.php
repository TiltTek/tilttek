<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use Override;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;
use Tilttek\Badges\ShareChannel;

/**
 * A channel that answers with a link naming the badge and the award's number.
 */
final class Channel implements ShareChannel
{
    #[Override]
    public function share(Award $award, Badge $badge): string
    {
        return \sprintf('https://example.org/shared/%s/%d', $badge->code, $award->number);
    }
}
