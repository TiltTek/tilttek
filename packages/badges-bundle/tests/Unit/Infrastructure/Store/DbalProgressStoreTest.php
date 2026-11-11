<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Badges\Store\ProgressStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProgressStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\Rows;
use Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalProgressStore::class)]
#[CoversClass(Rows::class)]
final class DbalProgressStoreTest extends ProgressStoreTestCase
{
    #[Override]
    protected function store(): ProgressStore
    {
        return new DbalProgressStore(Database::connection());
    }
}
