<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\CopyStore;

/**
 * A store that another process emptied between the insertion it refused and the reading that follows: it says every
 * copy is already there, and finds none.
 */
final class ForgetfulCopyStore implements CopyStore
{
    #[Override]
    public function add(Copy $copy): bool
    {
        return false;
    }

    #[Override]
    public function find(string $id): ?Copy
    {
        return null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return [];
    }

    #[Override]
    public function reassign(string $id, ResourceReference $from, ResourceReference $to): bool
    {
        return false;
    }

    #[Override]
    public function takeBack(string $id, ResourceReference $member): bool
    {
        return false;
    }

    #[Override]
    public function forget(Source $source, ?DateTimeImmutable $since): int
    {
        return 0;
    }

    #[Override]
    public function eraseOwner(ResourceReference $member): int
    {
        return 0;
    }
}
