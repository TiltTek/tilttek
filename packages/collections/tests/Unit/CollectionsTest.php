<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\CatalogReader;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Copy;
use Tilttek\Collections\CopyMove;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Holding;
use Tilttek\Collections\Item;
use Tilttek\Collections\SetCompleted;
use Tilttek\Collections\SetProgress;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;
use Tilttek\Collections\Tests\Unit\Fixture\Clock;
use Tilttek\Collections\Tests\Unit\Fixture\ForgetfulCopyStore;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;
use Tilttek\Collections\Tests\Unit\Fixture\MutableCatalog;
use Tilttek\Collections\Tests\Unit\Fixture\RacingCopyStore;

#[CoversClass(Collections::class)]
#[CoversClass(SetCompleted::class)]
#[CoversClass(CopyMove::class)]
final class CollectionsTest extends TestCase
{
    public function testAKeyGivesOneCopyHoweverManyTimesItIsGiven(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');

        $copy = $kit->collections->grant($member, 'pirate', Source::Purchase, 'order:1');
        self::assertSame(Copy::idFor(CollectionKey::fromString('order:1')), $copy->id);
        self::assertSame(['pirate', 'member:1', Source::Purchase, 'order:1', '2026-11-07 12:00', true, false], [$copy->item, $copy->owner->toString(), $copy->source, $copy->key, $copy->acquiredAt->format('Y-m-d H:i'), $copy->tradable, $copy->moved]);
        self::assertEquals($copy, $kit->collections->grant($member, 'pirate', Source::Purchase, CollectionKey::fromString('order:1'), new DateTimeImmutable('2027-01-01')));
        self::assertSame(['granted pirate to member:1'], $kit->recorder->events());

        $at = new DateTimeImmutable('2026-11-01 08:00 UTC');
        self::assertSame($at, $kit->collections->grant($member, 'pirate', Source::Gift, 'gift:1', $at)->acquiredAt);
        self::assertCount(2, $kit->collections->copiesOf($member));
        self::assertSame(2, $kit->collections->album($member)->holdings[1]->count);
    }

    public function testAKeyThatGaveSomethingElseIsRefused(): void
    {
        $kit = Kit::standard();
        $kit->collections->grant(Kit::ref('member:1'), 'pirate', Source::Purchase, 'order:1');

        foreach ([['member:1', 'captain'], ['member:2', 'pirate']] as [$member, $item]) {
            try {
                $kit->collections->grant(Kit::ref($member), $item, Source::Purchase, 'order:1');
                self::fail($member.' '.$item);
            } catch (CollectionRefused $collectionRefused) {
                self::assertSame('The key "order:1" already gave another copy.', $collectionRefused->getMessage());
            }
        }
    }

    public function testAKeyGivenAgainAfterATradeFindsTheTradedCopy(): void
    {
        $kit = Kit::standard();
        $copy = $kit->collections->grant(Kit::ref('member:1'), 'pirate', Source::Purchase, 'order:1');
        $kit->collections->move([new CopyMove($copy->id, Kit::ref('member:1'), Kit::ref('member:2'))]);

        self::assertSame('member:2', $kit->collections->grant(Kit::ref('member:1'), 'pirate', Source::Purchase, 'order:1')->owner->toString());
    }

    public function testACopyOfAnUnknownItemIsNotGiven(): void
    {
        $this->expectExceptionObject(NotFound::of('item', 'parrot'));

        Kit::standard()->collections->grant(Kit::ref('member:1'), 'parrot', Source::Granted, 'k');
    }

    public function testACopyIsBoundWhenAskedOrWhenItsItemIs(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');

        self::assertFalse($kit->collections->grant($member, 'pirate', Source::Reward, 'quest:1', tradable: false)->tradable);
        self::assertFalse($kit->collections->grant($member, 'medal', Source::Reward, 'quest:2')->tradable);
        self::assertTrue($kit->collections->isTradable($kit->collections->grant($member, 'pirate', Source::Reward, 'quest:3')->id));
        self::assertFalse($kit->collections->isTradable(Copy::idFor(CollectionKey::fromString('quest:1'))));
        self::assertFalse($kit->collections->isTradable('nothing'));
    }

    public function testASetCompletedIsToldOnceWithItsRewards(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');

        $kit->collections->grant($member, 'deckhand', Source::Granted, 'k1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k2');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k3');
        $kit->collections->grant(Kit::ref('member:2'), 'captain', Source::Granted, 'k4');
        self::assertCount(4, $kit->recorder->events());

        $kit->clock->set('2026-11-08 09:30:00 UTC');
        $kit->collections->grant($member, 'captain', Source::Granted, 'k5');
        $kit->collections->grant($member, 'captain', Source::Granted, 'k6');
        self::assertSame([
            'granted deckhand to member:1',
            'granted pirate to member:1',
            'granted pirate to member:1',
            'granted captain to member:2',
            'granted captain to member:1',
            'completed crew by member:1 at 2026-11-08 09:30: points xp 50, badge full-crew 1',
            'granted captain to member:1',
        ], $kit->recorder->events());

        $album = $kit->collections->album($member);
        $crew = $album->set('crew') ?? self::fail('No crew.');
        self::assertTrue($crew->isComplete());
        self::assertTrue($crew->completedOnce);
        self::assertSame(['pirate', 'captain'], array_map(static fn (Holding $holding): string => $holding->item->code, $album->doubles()));
    }

    public function testAnItemOfNoSetCompletesNothing(): void
    {
        $kit = Kit::standard();
        $kit->collections->grant(Kit::ref('member:1'), 'medal', Source::Granted, 'k1');

        self::assertSame(['granted medal to member:1'], $kit->recorder->events());
        self::assertSame([], $kit->completions->ofMember(Kit::ref('member:1')));
    }

    public function testASetIsGivenWholeUnderDerivedKeys(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $at = new DateTimeImmutable('2026-11-02 10:00 UTC');

        $copies = $kit->collections->grantSet($member, 'frost', Source::Exchange, 'exchange:9', $at);
        self::assertSame(['exchange:9#frost-d6'], array_map(static fn (Copy $copy): string => $copy->key, $copies));
        self::assertSame($at, $copies[0]->acquiredAt);
        self::assertSame(['granted frost-d6 to member:1', 'completed frost by member:1 at 2026-11-02 10:00: '], $kit->recorder->events());

        $kit->clock->set('2026-11-09 00:00 UTC');
        $crew = $kit->collections->grantSet($member, 'crew', Source::Purchase, CollectionKey::fromString('order:2'));
        self::assertSame(['order:2#deckhand', 'order:2#pirate', 'order:2#captain'], array_map(static fn (Copy $copy): string => $copy->key, $crew));
        self::assertSame('2026-11-09', $crew[2]->acquiredAt->format('Y-m-d'));
        self::assertSame([Source::Purchase], array_values(array_unique(array_map(static fn (Copy $copy): Source => $copy->source, $crew), \SORT_REGULAR)));
    }

    public function testAMemberArrivingGetsEveryBaseSet(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');

        $copies = $kit->collections->welcome($member, 'welcome:member:1', new DateTimeImmutable('2026-10-01 UTC'));
        self::assertSame(['welcome:member:1#ember#ember-d6', 'welcome:member:1#ember#ember-d20'], array_map(static fn (Copy $copy): string => $copy->key, $copies));
        self::assertSame([Source::Welcome, Source::Welcome], array_map(static fn (Copy $copy): Source => $copy->source, $copies));
        self::assertSame('2026-10-01', $copies[1]->acquiredAt->format('Y-m-d'));
        self::assertCount(2, $kit->collections->welcome($member, 'welcome:member:1'));
        self::assertCount(2, $kit->collections->copiesOf($member));
        self::assertSame('2026-11-07', $kit->collections->welcome(Kit::ref('member:2'), 'welcome:member:2')[0]->acquiredAt->format('Y-m-d'));
    }

    public function testACopyIsFoundByItsIdentifier(): void
    {
        $kit = Kit::standard();
        $copy = $kit->collections->grant(Kit::ref('member:1'), 'pirate', Source::Granted, 'k1');

        self::assertEquals($copy, $kit->collections->copy($copy->id));
        self::assertSame('member:1', $kit->collections->ownerOf($copy->id)?->toString());
        self::assertNull($kit->collections->ownerOf('nothing'));

        $this->expectExceptionObject(NotFound::of('copy', 'nothing'));
        $kit->collections->copy('nothing');
    }

    public function testCopiesMoveAllTogetherOrNotAtAll(): void
    {
        $kit = Kit::standard();
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $pirate = $kit->collections->grant($one, 'pirate', Source::Granted, 'k1');
        $deckhand = $kit->collections->grant($two, 'deckhand', Source::Granted, 'k2');

        try {
            $kit->collections->move([new CopyMove($pirate->id, $one, $two), new CopyMove($deckhand->id, $one, $two)]);
            self::fail('Moved.');
        } catch (CollectionRefused $collectionRefused) {
            self::assertSame(\sprintf('The copy "%s" does not belong to "member:1".', $deckhand->id), $collectionRefused->getMessage());
        }

        self::assertSame('member:1', $kit->collections->ownerOf($pirate->id)?->toString());

        try {
            $kit->collections->move([new CopyMove('nothing', $one, $two)]);
            self::fail('Moved.');
        } catch (CollectionRefused $collectionRefused) {
            self::assertSame('The copy "nothing" does not belong to "member:1".', $collectionRefused->getMessage());
        }

        $kit->collections->move([new CopyMove($pirate->id, $one, $two), new CopyMove($deckhand->id, $two, $one)]);
        self::assertSame('member:2', $kit->collections->ownerOf($pirate->id)?->toString());
        self::assertSame('member:1', $kit->collections->ownerOf($deckhand->id)?->toString());
        self::assertTrue($kit->collections->copy($pirate->id)->moved);
    }

    public function testAMoveThatCompletesASetTellsIt(): void
    {
        $kit = Kit::standard();
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $kit->collections->grant($one, 'deckhand', Source::Granted, 'k1');
        $kit->collections->grant($one, 'pirate', Source::Granted, 'k2');

        $captain = $kit->collections->grant($two, 'captain', Source::Granted, 'k3');
        $kit->clock->set('2026-12-01 10:00 UTC');

        $kit->collections->move([new CopyMove($captain->id, $two, $one)]);
        self::assertSame('completed crew by member:1 at 2026-12-01 10:00: points xp 50, badge full-crew 1', $kit->recorder->events()[3]);
    }

    public function testAMoveRacedByAnotherIsRefused(): void
    {
        $collections = new Collections(FixedCatalog::fromArray(Kit::definition()), new RacingCopyStore(), new InMemoryCompletionStore(), new Clock());
        $copy = $collections->grant(Kit::ref('member:1'), 'pirate', Source::Granted, 'k');

        $this->expectExceptionObject(CollectionRefused::because(\sprintf('The copy "%s" changed hands meanwhile.', $copy->id)));
        $collections->move([new CopyMove($copy->id, Kit::ref('member:1'), Kit::ref('member:2'))]);
    }

    public function testACopyIsTakenBackIfStillWithItsMember(): void
    {
        $kit = Kit::standard();
        $one = Kit::ref('member:1');
        $kit->collections->grant($one, 'pirate', Source::Purchase, 'order:1');
        $traded = $kit->collections->grant($one, 'deckhand', Source::Purchase, 'order:2');
        $kit->collections->move([new CopyMove($traded->id, $one, Kit::ref('member:2'))]);

        self::assertFalse($kit->collections->takeBack(Kit::ref('member:2'), 'order:1'));
        self::assertTrue($kit->collections->takeBack($one, 'order:1'));
        self::assertFalse($kit->collections->takeBack($one, CollectionKey::fromString('order:1')));
        self::assertFalse($kit->collections->takeBack(Kit::ref('member:2'), 'order:2'));
        self::assertSame([], $kit->collections->copiesOf($one));
    }

    public function testAnAlbumShowsEveryItemAndSet(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k2');
        $kit->collections->grant($member, 'ember-d6', Source::Granted, 'k3');
        $kit->collections->grant(Kit::ref('member:2'), 'captain', Source::Granted, 'k4');

        $album = $kit->collections->album($member);
        self::assertSame(['deckhand' => 0, 'pirate' => 2, 'captain' => 0, 'ember-d6' => 1, 'ember-d20' => 0, 'frost-d6' => 0, 'medal' => 0], array_combine(array_map(static fn (Holding $holding): string => $holding->item->code, $album->holdings), array_map(static fn (Holding $holding): int => $holding->count, $album->holdings)));
        self::assertSame(
            ['crew' => [['pirate'], ['deckhand', 'captain'], false], 'ember' => [['ember-d6'], ['ember-d20'], false], 'frost' => [[], ['frost-d6'], false]],
            array_combine(
                array_map(static fn (SetProgress $progress): string => $progress->set->code, $album->sets),
                array_map(static fn (SetProgress $progress): array => [array_map(static fn (Item $item): string => $item->code, $progress->owned), array_map(static fn (Item $item): string => $item->code, $progress->missing), $progress->completedOnce], $album->sets),
            ),
        );
    }

    public function testACompletedSetStaysCompletedOnceAfterATrade(): void
    {
        $kit = Kit::standard();
        $one = Kit::ref('member:1');
        $copy = $kit->collections->grantSet($one, 'frost', Source::Exchange, 'exchange:1')[0];
        $kit->collections->move([new CopyMove($copy->id, $one, Kit::ref('member:2'))]);

        $frost = $kit->collections->album($one)->set('frost') ?? self::fail('No frost.');
        self::assertFalse($frost->isComplete());
        self::assertTrue($frost->completedOnce);
        self::assertSame(['completed frost by member:2 at 2026-11-07 12:00: '], \array_slice($kit->recorder->events(), 2));
    }

    public function testTheCollectionsWorkWithoutAnObserver(): void
    {
        $collections = new Collections(FixedCatalog::fromArray(Kit::definition()), new InMemoryCopyStore(), new InMemoryCompletionStore(), new Clock());
        $collections->grantSet(Kit::ref('member:1'), 'frost', Source::Granted, 'k');

        self::assertTrue(($collections->album(Kit::ref('member:1'))->set('frost') ?? self::fail('No frost.'))->completedOnce);
    }

    public function testAKeyWhoseCopyVanishedIsRefused(): void
    {
        $collections = new Collections(FixedCatalog::fromArray(Kit::definition()), new ForgetfulCopyStore(), new InMemoryCompletionStore(), new Clock());

        $this->expectExceptionObject(CollectionRefused::because('The key "k" already gave another copy.'));
        $collections->grant(Kit::ref('member:1'), 'pirate', Source::Granted, 'k');
    }

    public function testEveryBaseSetIsGivenOnArrival(): void
    {
        $definition = Kit::definition();
        $definition['sets']['frost']['kind'] = 'base';
        $kit = new Kit(FixedCatalog::fromArray($definition));

        self::assertSame(['ember-d6', 'ember-d20', 'frost-d6'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->welcome(Kit::ref('member:1'), 'welcome:1')));
    }

    public function testASetWithoutItemsIsNeverCompleted(): void
    {
        $definition = Kit::definition();
        $definition['sets']['future'] = ['name' => 'Future'];
        $kit = new Kit(FixedCatalog::fromArray($definition));
        $kit->collections->grant(Kit::ref('member:1'), 'medal', Source::Granted, 'k1');

        self::assertSame([], $kit->completions->ofMember(Kit::ref('member:1')));
        self::assertFalse(($kit->collections->album(Kit::ref('member:1'))->set('future') ?? self::fail('No future.'))->isComplete());
    }

    public function testOnlyTheSetsOfTheItemsGivenAreChecked(): void
    {
        $definition = Kit::definition();
        $provider = new MutableCatalog(CatalogReader::read($definition));
        $kit = new Kit($provider);
        $member = Kit::ref('member:1');
        $kit->collections->grant($member, 'deckhand', Source::Granted, 'k1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k2');

        $definition['items']['captain']['sets'] = [];
        $provider->catalog = CatalogReader::read($definition);
        $kit->collections->grant($member, 'medal', Source::Granted, 'k3');
        self::assertSame([], $kit->completions->ofMember($member));

        $kit->collections->grant($member, 'pirate', Source::Granted, 'k4');
        self::assertTrue(($kit->collections->album($member)->set('crew') ?? self::fail('No crew.'))->completedOnce);
    }
}
