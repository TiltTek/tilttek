<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Quests\Store\CompletionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCompletionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalCompletionStore::class)]
#[CoversClass(Rows::class)]
final class DbalCompletionStoreTest extends CompletionStoreTestCase
{
    protected function store(): CompletionStore
    {
        return new DbalCompletionStore(Database::connection());
    }
}
