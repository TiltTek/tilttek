<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalChoiceStore;
use Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalChoiceStore::class)]
final class DbalChoiceStoreTest extends ChoiceStoreTestCase
{
    #[Override]
    protected function store(): ChoiceStore
    {
        return new DbalChoiceStore(Database::connection());
    }
}
