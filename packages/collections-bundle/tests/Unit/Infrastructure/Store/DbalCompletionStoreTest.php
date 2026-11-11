<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Collections\Store\CompletionStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCompletionStore;
use Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalCompletionStore::class)]
final class DbalCompletionStoreTest extends CompletionStoreTestCase
{
    #[Override]
    protected function store(): CompletionStore
    {
        return new DbalCompletionStore(Database::connection());
    }
}
