<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Collections\Store\CopyStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCopyStore;
use Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalCopyStore::class)]
final class DbalCopyStoreTest extends CopyStoreTestCase
{
    #[Override]
    protected function store(): CopyStore
    {
        return new DbalCopyStore(Database::connection());
    }
}
