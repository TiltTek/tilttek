<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Quests\Store\EnrolmentStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalEnrolmentStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalEnrolmentStore::class)]
#[CoversClass(Rows::class)]
final class DbalEnrolmentStoreTest extends EnrolmentStoreTestCase
{
    protected function store(): EnrolmentStore
    {
        return new DbalEnrolmentStore(Database::connection());
    }
}
