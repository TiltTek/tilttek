<?php

declare(strict_types=1);

namespace Tilttek\Kudos\TimeZone;

use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Kudos\Contract\MemberTimeZones;

/**
 * Every member's day counted in the site's time zone — UTC unless another is given.
 */
final readonly class SiteTimeZone implements MemberTimeZones
{
    public function __construct(
        private DateTimeZone $zone = new DateTimeZone('UTC'),
    ) {
    }

    #[Override]
    public function of(ResourceReference $member): DateTimeZone
    {
        return $this->zone;
    }
}
