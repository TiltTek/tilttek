<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Contract\FirstActions;
use Tilttek\Referrals\FirstAction;

/**
 * First actions told by the test, by member reference.
 */
final class Actions implements FirstActions
{
    /**
     * @var array<string, FirstAction>
     */
    public array $actions = [];

    #[Override]
    public function firstActionOf(ResourceReference $member): ?FirstAction
    {
        return $this->actions[$member->toString()] ?? null;
    }
}
