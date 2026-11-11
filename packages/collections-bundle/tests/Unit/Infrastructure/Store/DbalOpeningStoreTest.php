<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalOpeningStore;
use Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalOpeningStore::class)]
final class DbalOpeningStoreTest extends OpeningStoreTestCase
{
    #[Override]
    protected function store(): OpeningStore
    {
        return new DbalOpeningStore(Database::connection());
    }
}
