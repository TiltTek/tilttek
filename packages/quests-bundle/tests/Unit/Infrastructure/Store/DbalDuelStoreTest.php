<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalDuelStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalDuelStore::class)]
#[CoversClass(Rows::class)]
final class DbalDuelStoreTest extends DuelStoreTestCase
{
    protected function store(): DuelStore
    {
        return new DbalDuelStore(Database::connection());
    }
}
