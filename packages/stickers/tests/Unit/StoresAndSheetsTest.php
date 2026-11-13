<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\Event\SetCompleted;
use Tilttek\Sets\Exception\UnknownSet;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Tier;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Placement;
use Tilttek\Stickers\Policy\InMemorySurfacePolicy;
use Tilttek\Stickers\Sticker;
use Tilttek\Stickers\StickerSheets;
use Tilttek\Stickers\Store\InMemoryOwnershipStore;
use Tilttek\Stickers\Store\InMemoryPlacementStore;
use Tilttek\Stickers\Store\InMemoryStickerCatalog;
use Tilttek\Stickers\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Stickers\Transform;

#[CoversClass(InMemoryStickerCatalog::class)]
#[CoversClass(InMemoryOwnershipStore::class)]
#[CoversClass(InMemoryPlacementStore::class)]
#[CoversClass(InMemorySurfacePolicy::class)]
#[CoversClass(StickerSheets::class)]
final class StoresAndSheetsTest extends TestCase
{
    public function testTheCatalogueKeepsItsOrderAndRefusesACodeTwice(): void
    {
        $catalog = new InMemoryStickerCatalog([new Sticker('b', 'b.png', 'B'), new Sticker('a', 'a.png', 'A')]);

        self::assertSame(['b', 'a'], array_map(static fn (Sticker $sticker): string => $sticker->code, $catalog->all()));
        self::assertSame([0, 1], array_keys($catalog->all()));
        self::assertSame('A', $catalog->get('a')?->alt);
        self::assertNull($catalog->get('c'));

        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains('The catalogue names the sticker "a" twice.');

        new InMemoryStickerCatalog([new Sticker('a', 'a.png', 'A'), new Sticker('a', 'b.png', 'B')]);
    }

    public function testTheOwnershipStoreKeepsEachStickerOncePerHolder(): void
    {
        $store = new InMemoryOwnershipStore();
        $one = ResourceReference::of('member', '1');
        $two = ResourceReference::of('member', '2');

        self::assertTrue($store->grant($one, 'b'));
        self::assertTrue($store->grant($one, '42'));
        self::assertFalse($store->grant($one, 'b'));
        self::assertTrue($store->grant($two, 'b'));
        self::assertSame(['b', '42'], $store->owned($one));
        self::assertTrue($store->owns($one, '42'));
        self::assertFalse($store->owns($two, '42'));
        self::assertSame([], $store->owned(ResourceReference::of('member', '3')));
        self::assertTrue($store->revoke($one, 'b'));
        self::assertFalse($store->revoke($one, 'b'));
        self::assertFalse($store->revoke($two, '42'));
        self::assertFalse($store->owns($one, 'b'));
        self::assertSame(1, $store->eraseHolder($one));
        self::assertSame(0, $store->eraseHolder($one));
        self::assertSame(['b'], $store->owned($two));
    }

    public function testThePlacementStoreKeepsThePlacingOrder(): void
    {
        $store = new InMemoryPlacementStore();
        $one = ResourceReference::of('member', '1');
        $two = ResourceReference::of('member', '2');
        $profile = ResourceReference::of('profile', '1');
        $message = ResourceReference::of('message', '1');
        $at = new DateTimeImmutable();

        $store->add(new Placement('p1', $one, 's', $profile, new Transform(0, 0), $at));
        $store->add(new Placement('p2', $two, 's', $profile, new Transform(0, 0), $at));
        $store->add(new Placement('p3', $one, 's', $message, new Transform(0, 0), $at));
        $store->replace(new Placement('p1', $one, 's', $profile, new Transform(5, 5), $at));

        self::assertSame(['p1', 'p2'], array_map(static fn (Placement $placement): string => $placement->id, $store->onSurface($profile)));
        self::assertSame([0, 1], array_keys($store->onSurface($profile)));
        self::assertSame(['p1', 'p3'], array_map(static fn (Placement $placement): string => $placement->id, $store->ofHolder($one)));
        self::assertSame([0, 1], array_keys($store->ofHolder($one)));
        self::assertSame(5, $store->get('p1')?->transform->x);
        self::assertNull($store->get('p9'));
        self::assertTrue($store->remove('p2'));
        self::assertFalse($store->remove('p2'));
        self::assertSame(1, $store->eraseSurface($message));
        self::assertSame(1, $store->eraseHolder($one));
        self::assertSame(0, $store->eraseHolder($one));
        self::assertSame([], $store->onSurface($profile));
    }

    public function testTheSurfacePolicyAllowsOnlyTheSurfacesAssigned(): void
    {
        $policy = new InMemorySurfacePolicy();
        $holder = ResourceReference::of('member', '1');
        $profile = ResourceReference::of('profile', '1');

        self::assertFalse($policy->allows($holder, $profile));

        $policy->assign($profile, $holder);
        self::assertTrue($policy->allows($holder, $profile));
        self::assertFalse($policy->allows(ResourceReference::of('member', '2'), $profile));
        self::assertFalse($policy->allows($holder, ResourceReference::of('profile', '2')));
    }

    public function testASheetIsASetCompletedFromTheStickersOwned(): void
    {
        $events = new RecordingDispatcher();
        $sets = new Sets(new InMemoryDefinitionStore(), new InMemoryCompletionStore(), events: $events);
        $sets->define(new SetDefinition('dragons', 1, ['red-dragon', 'blue-dragon'], [new Tier('first', required: 1)]));

        $ownerships = new InMemoryOwnershipStore();
        $sheets = new StickerSheets($sets, $ownerships);
        $holder = ResourceReference::of('member', '1');

        $ownerships->grant($holder, 'red-dragon');
        $ownerships->grant($holder, 'd20');
        self::assertSame(50, $sheets->progress($holder, 'dragons')->evaluation->percent());
        self::assertCount(1, $sheets->record($holder, 'dragons')->recorded);

        $ownerships->grant($holder, 'blue-dragon');
        $progress = $sheets->record($holder, 'dragons');
        self::assertTrue($progress->completedOnce());
        self::assertSame([SetCompleted::class], array_values(array_map(get_class(...), array_filter($events->events, static fn (object $event): bool => $event instanceof SetCompleted))));
        self::assertCount(2, $events->events);

        $this->expectException(UnknownSet::class);

        $sheets->progress($holder, 'unicorns');
    }
}
