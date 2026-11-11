<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Where the consents are kept that members give other applications to read their badges, each until withdrawn.
 */
interface GrantStore
{
    /**
     * Keeps the consent; given again, it stays given.
     */
    public function grant(ResourceReference $member, string $client, DateTimeImmutable $at): void;

    /**
     * Withdraws the consent; false when there was none.
     */
    public function withdraw(ResourceReference $member, string $client): bool;

    public function allows(ResourceReference $member, string $client): bool;

    /**
     * The applications the member allowed, by name.
     *
     * @return list<string>
     */
    public function clients(ResourceReference $member): array;

    /**
     * @return int how many consents of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
