<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalAwardStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\Rows;
use Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalAwardStore::class)]
#[CoversClass(Rows::class)]
final class DbalAwardStoreTest extends AwardStoreTestCase
{
    #[Override]
    protected function store(): AwardStore
    {
        return new DbalAwardStore(Database::connection());
    }
}
