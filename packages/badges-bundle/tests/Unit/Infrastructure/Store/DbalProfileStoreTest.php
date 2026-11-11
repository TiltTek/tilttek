<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Badges\Store\ProfileStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProfileStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\Rows;
use Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalProfileStore::class)]
#[CoversClass(Rows::class)]
final class DbalProfileStoreTest extends ProfileStoreTestCase
{
    #[Override]
    protected function store(): ProfileStore
    {
        return new DbalProfileStore(Database::connection());
    }
}
