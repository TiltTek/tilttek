<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Catalog;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\CatalogReader;
use Tilttek\Collections\Copy;
use Tilttek\Collections\SetCompleted;
use Tilttek\Collections\Source;
use Tilttek\CollectionsBundle\Infrastructure\Dice\CollectionDiceAppearance;
use Tilttek\CollectionsBundle\Infrastructure\Event\CopyGranted;
use Tilttek\CollectionsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\CollectionsBundle\Infrastructure\Exchange\CollectionsInventory;
use Tilttek\CollectionsBundle\Infrastructure\Twig\CollectionsExtension;
use Tilttek\CollectionsBundle\Tests\Unit\Fixture\Kit;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Trade\ItemMove;

#[CoversClass(CollectionsInventory::class)]
#[CoversClass(CollectionDiceAppearance::class)]
#[CoversClass(DispatchingObserver::class)]
#[CoversClass(CopyGranted::class)]
#[CoversClass(CollectionsExtension::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheCopiesAreTheInventoryOfTrades(): void
    {
        $kit = Kit::standard();
        $inventory = new CollectionsInventory($kit->collections);
        $one = ResourceReference::fromString('member:1');
        $two = ResourceReference::fromString('member:2');
        $pirate = $kit->collections->grant($one, 'pirate', Source::Granted, 'k1');
        $bound = $kit->collections->grant($one, 'deckhand', Source::Reward, 'k2', tradable: false);

        self::assertSame('member:1', $inventory->ownerOf($pirate->reference())?->toString());
        self::assertNull($inventory->ownerOf(ResourceReference::of('card', $pirate->id)));
        self::assertTrue($inventory->isTradable($pirate->reference()));
        self::assertFalse($inventory->isTradable($bound->reference()));
        self::assertFalse($inventory->isTradable(ResourceReference::of('card', $pirate->id)));

        $inventory->move([new ItemMove($pirate->reference(), $one, $two)]);
        self::assertSame('member:2', $kit->collections->ownerOf($pirate->id)?->toString());

        foreach ([[new ItemMove($pirate->reference(), $one, $two), \sprintf('The copy "%s" does not belong to "member:1".', $pirate->id)], [new ItemMove(ResourceReference::of('card', $bound->id), $one, $two), \sprintf('"card:%s" is not a copy of a collection.', $bound->id)]] as [$move, $detail]) {
            try {
                $inventory->move([new ItemMove($bound->reference(), $one, $two), $move]);
                self::fail($detail);
            } catch (ExchangeRefused $exchangeRefused) {
                self::assertSame(Refusal::NotOwned, $exchangeRefused->refusal);
                self::assertStringContainsString($detail, $exchangeRefused->getMessage());
            }
        }

        self::assertSame('member:1', $kit->collections->ownerOf($bound->id)?->toString());
    }

    public function testTheChosenDieDressesTheRoll(): void
    {
        $kit = Kit::standard();
        $member = ResourceReference::fromString('member:1');
        $appearance = new CollectionDiceAppearance($kit->dice, 'fr');
        $die = $kit->collections->grant($member, 'ember-d6', Source::Welcome, 'k1');
        $plain = $kit->collections->grant($member, 'plain-d20', Source::Welcome, 'k2');

        self::assertNull($appearance->appearance($member, 6));
        $kit->dice->choose($member, $die->id);
        $kit->dice->choose($member, $plain->id);
        self::assertEquals(new DieAppearance('d6 de braise', '#c0392b', 'resin', '/img/ember-d6.webp'), $appearance->appearance($member, 6));
        self::assertEquals(new DieAppearance('Plain d20'), $appearance->appearance($member, 20));
        self::assertEquals(new DieAppearance('Ember d6', '#c0392b', 'resin', '/img/ember-d6.webp'), new CollectionDiceAppearance($kit->dice, 'en')->appearance($member, 6));
    }

    public function testADieNoLongerADieIsNotDressed(): void
    {
        $definition = Kit::definition();
        $provider = new class(CatalogReader::read($definition)) implements CatalogProvider {
            public function __construct(
                public Catalog $catalog,
            ) {
            }

            #[Override]
            public function catalog(): Catalog
            {
                return $this->catalog;
            }
        };
        $kit = new Kit($provider);
        $member = ResourceReference::fromString('member:1');
        $kit->dice->choose($member, $kit->collections->grant($member, 'ember-d6', Source::Welcome, 'k1')->id);

        $definition['items']['ember-d6'] = ['name' => 'Ember token', 'rarity' => 'common', 'sets' => ['ember']];
        $provider->catalog = CatalogReader::read($definition);

        self::assertNull(new CollectionDiceAppearance($kit->dice, 'en')->appearance($member, 6));
    }

    public function testWhatTheCollectionsDecideBecomesEvents(): void
    {
        $kit = Kit::standard();
        $member = ResourceReference::fromString('member:1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k1');
        $kit->collections->grant($member, 'deckhand', Source::Granted, 'k2');

        $events = $kit->events->events();

        self::assertSame([CopyGranted::class, CopyGranted::class, SetCompleted::class], array_map(static fn (object $event): string => $event::class, $events));
        self::assertInstanceOf(CopyGranted::class, $events[0]);
        self::assertSame('pirate', $events[0]->copy->item);
        self::assertInstanceOf(SetCompleted::class, $events[2]);
        self::assertSame('crew', $events[2]->set->code);
    }

    public function testTheTemplatesReachTheCollections(): void
    {
        $kit = Kit::standard();
        $extension = new CollectionsExtension($kit->collections, $kit->packs, $kit->daily, 'gallery');
        $kit->collections->grant(ResourceReference::fromString('member:1'), 'pirate', Source::Granted, 'k1');

        self::assertSame('gallery', $extension->domain());
        self::assertSame(1, $extension->album('member:1')->holdings[0]->count);
        self::assertSame('pirate', $extension->today()->item);
        self::assertSame('Pirate', $extension->item('pirate')->name->in('en'));
        self::assertSame('Corsaire', $extension->text($extension->item('pirate')->name, 'fr'));
        self::assertSame(['common' => 0.75, 'rare' => 0.25], $extension->odds('booster')->rarities());
        self::assertInstanceOf(Copy::class, $kit->collections->copiesOf(ResourceReference::fromString('member:1'))[0]);
    }
}
