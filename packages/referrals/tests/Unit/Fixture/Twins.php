<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Contract\SamePerson;

/**
 * Pairs of accounts the test tells are one person, in the order referrer then newcomer.
 */
final class Twins implements SamePerson
{
    /**
     * @var list<array{string, string}>
     */
    public array $pairs = [];

    #[Override]
    public function same(ResourceReference $referrer, ResourceReference $newcomer): bool
    {
        return \in_array([$referrer->toString(), $newcomer->toString()], $this->pairs, true);
    }
}
