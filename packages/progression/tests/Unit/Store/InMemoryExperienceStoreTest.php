<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\Total;

#[CoversClass(InMemoryExperienceStore::class)]
#[CoversClass(Total::class)]
#[CoversClass(Gain::class)]
final class InMemoryExperienceStoreTest extends ExperienceStoreTestCase
{
    protected function store(): ExperienceStore
    {
        return new InMemoryExperienceStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryExperienceStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryExperienceStore(), $store);
    }
}
