<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Dice;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\Collections\Dice\InMemoryChoiceStore;

#[CoversClass(InMemoryChoiceStore::class)]
final class InMemoryChoiceStoreTest extends ChoiceStoreTestCase
{
    #[Override]
    protected function store(): ChoiceStore
    {
        return new InMemoryChoiceStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryChoiceStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryChoiceStore(), $store);
    }
}
