<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Exception\PreparationConflict;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationQuery;
use Tilttek\SessionPrep\Store\InMemoryPreparationStore;

#[CoversClass(InMemoryPreparationStore::class)]
#[CoversClass(PreparationConflict::class)]
final class InMemoryPreparationStoreTest extends TestCase
{
    public function testAPreparationIsKeptAtTheNextVersion(): void
    {
        $store = new InMemoryPreparationStore();
        self::assertNull(new InMemoryPreparationStore()->preparation('p'));

        $kept = $store->save(new Preparation('p', 'c', 'm', 'Title'));
        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->preparation('p'));
        $again = $store->save($kept->withTitle('Again'));
        self::assertSame([2, 'Again'], [$again->version, $again->title]);
        self::assertSame([$again], [...$store->preparations(new PreparationQuery(), 1)]);
    }

    public function testANewPreparationOfAKeptIdentifierIsAConflict(): void
    {
        $store = new InMemoryPreparationStore();
        $store->save(new Preparation('p', 'c', 'm', 'Title'));

        $this->expectExceptionObject(PreparationConflict::changed('p'));

        $store->save(new Preparation('p', 'c', 'm', 'Other'));
    }

    public function testAStalePreparationIsAConflict(): void
    {
        $store = new InMemoryPreparationStore();
        $kept = $store->save(new Preparation('p', 'c', 'm', 'Title'));
        $store->save($kept);

        $this->expectException(PreparationConflict::class);

        $store->save($kept);
    }

    public function testAPreparationIsDeletedAtItsVersion(): void
    {
        $store = new InMemoryPreparationStore();
        $kept = $store->save(new Preparation('p', 'c', 'm', 'Title'));
        $store->save(new Preparation('q', 'c', 'm', 'Title'));

        $store->delete($kept);

        self::assertNull($store->preparation('p'));
        self::assertNotNull($store->preparation('q'));
    }

    public function testDeletingAStalePreparationIsAConflict(): void
    {
        $store = new InMemoryPreparationStore();
        $kept = $store->save(new Preparation('p', 'c', 'm', 'Title'));
        $store->save($kept);

        try {
            $store->delete($kept);
            self::fail('A stale delete is not refused.');
        } catch (PreparationConflict) {
            self::assertNotNull($store->preparation('p'));
        }
    }

    public function testDeletingAPreparationNotKeptIsAConflict(): void
    {
        $this->expectExceptionObject(PreparationConflict::changed('p'));

        new InMemoryPreparationStore()->delete(new Preparation('p', 'c', 'm', 'Title'));
    }

    public function testThePreparationsAreReadByIdentifierUpToTheLimit(): void
    {
        $store = new InMemoryPreparationStore();

        foreach (['p10', 'p2', 'p1', 'q1'] as $id) {
            $store->save(new Preparation($id, 'p1' === $id ? 'other' : 'c', 'm', 'Title'));
        }

        $ids = static fn (iterable $preparations): array => array_map(static fn (Preparation $preparation): string => $preparation->id, [...$preparations]);

        self::assertSame(['p1', 'p10', 'p2', 'q1'], $ids($store->preparations(new PreparationQuery(), 10)));
        self::assertSame(['p10', 'p2'], $ids($store->preparations(new PreparationQuery('c'), 2)));
        self::assertSame(['p1'], $ids($store->preparations(new PreparationQuery(), 1)));
    }

    public function testAReadIsOfOneAtLeast(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('A store reads at least one preparation at once.'));

        new InMemoryPreparationStore()->preparations(new PreparationQuery(), 0);
    }
}
