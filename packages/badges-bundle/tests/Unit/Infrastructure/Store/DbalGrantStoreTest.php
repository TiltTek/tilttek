<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Badges\Store\GrantStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalGrantStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\Rows;
use Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalGrantStore::class)]
#[CoversClass(Rows::class)]
final class DbalGrantStoreTest extends GrantStoreTestCase
{
    #[Override]
    protected function store(): GrantStore
    {
        return new DbalGrantStore(Database::connection());
    }
}
