<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Pack;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\PackOpening;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Pack\PendingOpening;
use Tilttek\Collections\Pack\SecureSeeds;
use Tilttek\Collections\Source;
use Tilttek\Collections\Tests\Unit\Fixture\FixedSeeds;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;
use Tilttek\Collections\Tests\Unit\Fixture\RacingOpeningStore;

#[CoversClass(Packs::class)]
#[CoversClass(PendingOpening::class)]
#[CoversClass(SecureSeeds::class)]
final class PacksTest extends TestCase
{
    public function testAPackPublishesItsOdds(): void
    {
        $kit = Kit::standard();

        self::assertSame(['common' => 0.5, 'rare' => 0.5], $kit->packs->odds('crew-pack')->rarities());
        self::assertSame(['common' => 0.7, 'rare' => 0.25, 'legendary' => 0.05], $kit->packs->odds('booster')->rarities());

        $this->expectExceptionObject(NotFound::of('pack', 'chest'));
        $kit->packs->odds('chest');
    }

    public function testTheCommitmentIsShownBeforeTheMemberGivesTheClientSeed(): void
    {
        $seed = str_repeat('s', 32);
        $kit = Kit::standard(seeds: new FixedSeeds([$seed]));
        $member = Kit::ref('member:1');

        $pending = $kit->packs->commit($member, 'booster', 'shop:1');
        self::assertSame(['shop:1', 'member:1', 'booster', bin2hex($seed)], [$pending->key, $pending->member->toString(), $pending->pack, $pending->seed]);
        self::assertSame(hash('sha256', $seed), $pending->commitment());
        self::assertSame([], $kit->collections->copiesOf($member));

        $opening = $kit->packs->open($pending, 'my lucky words');
        self::assertSame($this->draw('booster', $seed, 'my lucky words', 'shop:1', 3), $opening->items);
        self::assertSame(bin2hex($seed), $opening->seed);
        self::assertSame('my lucky words', $opening->clientSeed);
        self::assertSame($pending->commitment(), $opening->commitment);
        self::assertSame(Packs::commitment($seed), $opening->commitment);
        self::assertSame(['shop:1#1', 'shop:1#2', 'shop:1#3'], array_map(static fn (string $id): string => $kit->collections->copy($id)->key, $opening->copies));
        self::assertSame([Source::Pack], array_values(array_unique(array_map(static fn (string $id): Source => $kit->collections->copy($id)->source, $opening->copies), \SORT_REGULAR)));
        self::assertSame($opening->items, array_map(static fn (string $id): string => $kit->collections->copy($id)->item, $opening->copies));
        self::assertSame(['shop:1', 'member:1', 'booster', '2026-11-07 12:00'], [$opening->key, $opening->member->toString(), $opening->pack, $opening->openedAt->format('Y-m-d H:i')]);
        self::assertEquals($opening, $kit->openings->find('shop:1'));
        self::assertTrue($kit->packs->verify($opening));
        self::assertEquals([$opening], $kit->packs->openingsOf($member));
    }

    public function testTheClientSeedChangesTheDrawOfTheSameCommitment(): void
    {
        $seed = str_repeat('s', 32);
        $drawn = [];

        foreach (range(1, 8) as $try) {
            $kit = Kit::standard(seeds: new FixedSeeds([$seed]));
            $drawn[] = $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'booster', 'shop:1'), 'words '.$try)->items;
        }

        self::assertGreaterThan(1, \count(array_unique(array_map(serialize(...), $drawn))));
    }

    public function testTheKeyChangesTheDrawOfTheSameSeeds(): void
    {
        $seed = str_repeat('s', 32);

        self::assertNotSame($this->draw('booster', $seed, 'words', 'shop:1', 3), $this->draw('booster', $seed, 'words', 'shop:2', 3));

        $kit = Kit::standard(seeds: new FixedSeeds([$seed, $seed]));
        $one = $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'booster', 'shop:1'), 'words');
        $two = $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'booster', 'shop:2'), 'words');

        self::assertSame($this->draw('booster', $seed, 'words', 'shop:1', 3), $one->items);
        self::assertSame($this->draw('booster', $seed, 'words', 'shop:2', 3), $two->items);
    }

    public function testACommitmentForAPackTheCatalogDoesNotHold(): void
    {
        $this->expectExceptionObject(NotFound::of('pack', 'chest'));

        Kit::standard()->packs->commit(Kit::ref('member:1'), 'chest', 'shop:1');
    }

    public function testACommitmentUnderAKeyThatDoesNotReadIsRefused(): void
    {
        $this->expectExceptionObject(InvalidCollection::because('A collection key cannot contain whitespace, control characters or "#"; "shop#1" does.'));

        Kit::standard()->packs->commit(Kit::ref('member:1'), 'booster', 'shop#1');
    }

    public function testTheSameKeyOpensThePackOnce(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $pending = $kit->packs->commit($member, 'crew-pack', 'shop:1');
        $opening = $kit->packs->open($pending, 'mine', new DateTimeImmutable('2026-11-01 UTC'));

        self::assertSame('2026-11-01', $opening->openedAt->format('Y-m-d'));
        self::assertSame($this->draw('crew-pack', str_pad('seed-0', 16, '.'), 'mine', 'shop:1', 2), $opening->items);
        self::assertEquals($opening, $kit->packs->open($pending, 'mine'));
        self::assertCount(2, $kit->collections->copiesOf($member));

        foreach ([['member:1', 'booster'], ['member:2', 'crew-pack']] as [$who, $pack]) {
            try {
                $kit->packs->open(new PendingOpening('shop:1', Kit::ref($who), $pack, $pending->seed), 'mine');
                self::fail($who);
            } catch (CollectionRefused $collectionRefused) {
                self::assertSame('The key "shop:1" already opened another pack.', $collectionRefused->getMessage());
            }
        }
    }

    public function testAnOpeningThatLosesTheRaceForItsKeyReturnsTheWinnerAndGivesNothing(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $seed = bin2hex(str_pad('seed-0', 16, '.'));
        $winner = new PackOpening('shop:1', $member, 'crew-pack', $seed, Packs::commitment(str_pad('seed-0', 16, '.')), 'mine', ['pirate', 'deckhand'], ['c1', 'c2'], new DateTimeImmutable('2026-11-01 UTC'));
        $packs = new Packs($kit->collections, new RacingOpeningStore($winner), new FixedSeeds(), $kit->clock);

        self::assertSame($winner, $packs->open($packs->commit($member, 'crew-pack', 'shop:1'), 'mine'));
        self::assertSame([], $kit->collections->copiesOf($member));
    }

    public function testAnOpeningThatLosesTheRaceToAnotherClientSeedIsRefusedAndGivesNothing(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $seed = str_pad('seed-0', 16, '.');
        $winner = new PackOpening('shop:1', $member, 'crew-pack', bin2hex($seed), Packs::commitment($seed), 'theirs', ['pirate', 'deckhand'], [], new DateTimeImmutable('2026-11-01 UTC'));
        $packs = new Packs($kit->collections, new RacingOpeningStore($winner), new FixedSeeds(), $kit->clock);

        try {
            $packs->open($packs->commit($member, 'crew-pack', 'shop:1'), 'mine');
            self::fail('Opened.');
        } catch (CollectionRefused $collectionRefused) {
            self::assertSame('The key "shop:1" was opened from another commitment or client seed.', $collectionRefused->getMessage());
        }

        self::assertSame([], $kit->collections->copiesOf($member));
    }

    public function testAClientSeedIsCheckedBeforeTheOpeningKeptUnderTheKeyIsRead(): void
    {
        $kit = Kit::standard();
        $pending = $kit->packs->commit(Kit::ref('member:1'), 'crew-pack', 'shop:1');
        $kit->packs->open($pending, 'mine');

        $this->expectExceptionObject(InvalidCollection::because('A client seed is 1 to 256 bytes of UTF-8 text.'));

        $kit->packs->open($pending, '');
    }

    public function testAWinnerGoneBeforeItIsReadIsRefused(): void
    {
        $kit = Kit::standard();
        $packs = new Packs($kit->collections, new RacingOpeningStore(null), new FixedSeeds(), $kit->clock);

        $this->expectExceptionObject(CollectionRefused::because('The key "shop:1" is being opened by another request.'));

        $packs->open($packs->commit(Kit::ref('member:1'), 'crew-pack', 'shop:1'), 'mine');
    }

    public function testAWinnerStillGivingItsCopiesIsReturnedOnlyOnceTheyAreGiven(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $seed = str_pad('seed-0', 16, '.');
        $items = $this->draw('crew-pack', $seed, 'mine', 'shop:1', 2);
        $winner = new PackOpening('shop:1', $member, 'crew-pack', bin2hex($seed), Packs::commitment($seed), 'mine', $items, [], new DateTimeImmutable('2026-11-01 UTC'));
        $store = new RacingOpeningStore($winner);
        $packs = new Packs($kit->collections, $store, new FixedSeeds(), $kit->clock);

        // The winner reserved the key and has not given its copies yet, or stopped: the loser gives them under the same
        // keys, then returns the opening complete, with exactly its copies.
        $opening = $packs->open($packs->commit($member, 'crew-pack', 'shop:1'), 'mine');
        $given = $kit->collections->copiesOf($member);

        self::assertSame([Copy::idFor(CollectionKey::fromString('shop:1')->derive('1')), Copy::idFor(CollectionKey::fromString('shop:1')->derive('2'))], $opening->copies);
        self::assertSame($items, $opening->items);
        self::assertSame($opening->copies, array_map(static fn (Copy $copy): string => $copy->id, $given));
        self::assertSame($items, array_map(static fn (Copy $copy): string => $copy->item, $given));
        self::assertSame($opening->copies, $store->inner->find('shop:1')?->copies);
    }

    public function testAnOpeningReservedButNotGivenIsGivenWhenOpenedAgain(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $seed = str_pad('seed-0', 16, '.');
        $items = $this->draw('crew-pack', $seed, 'mine', 'shop:1', 2);
        $kit->openings->add(new PackOpening('shop:1', $member, 'crew-pack', bin2hex($seed), Packs::commitment($seed), 'mine', $items, [], new DateTimeImmutable('2026-11-01 UTC')));

        $opening = $kit->packs->open($kit->packs->commit($member, 'crew-pack', 'shop:1'), 'mine');

        self::assertSame($items, array_map(static fn (string $id): string => $kit->collections->copy($id)->item, $opening->copies));
        self::assertSame('2026-11-01', $kit->collections->copy($opening->copies[0])->acquiredAt->format('Y-m-d'));
        self::assertSame($opening->copies, $kit->openings->find('shop:1')?->copies);
        self::assertCount(2, $kit->collections->copiesOf($member));
    }

    public function testAnOpeningKeptUnderTheKeyIsReturnedWithoutBeingAddedAgain(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $store = new class implements OpeningStore {
            public int $additions = 0;

            private readonly InMemoryOpeningStore $inner;

            public function __construct()
            {
                $this->inner = new InMemoryOpeningStore();
            }

            #[Override]
            public function add(PackOpening $opening): bool
            {
                ++$this->additions;

                return $this->inner->add($opening);
            }

            #[Override]
            public function complete(string $key, array $copies): void
            {
                $this->inner->complete($key, $copies);
            }

            #[Override]
            public function find(string $key): ?PackOpening
            {
                return $this->inner->find($key);
            }

            #[Override]
            public function ofMember(ResourceReference $member): array
            {
                return $this->inner->ofMember($member);
            }

            #[Override]
            public function eraseMember(ResourceReference $member): int
            {
                return $this->inner->eraseMember($member);
            }
        };
        $packs = new Packs($kit->collections, $store, new FixedSeeds([str_pad('seed-0', 16, '.'), str_pad('seed-0', 16, '.')]), $kit->clock);
        $first = $packs->open($packs->commit($member, 'crew-pack', 'shop:1'), 'mine');

        self::assertEquals($first, $packs->open($packs->commit($member, 'crew-pack', 'shop:1'), 'mine'));
        self::assertSame(1, $store->additions);
    }

    public function testAKeyOpenedFromAnotherCommitmentIsRefused(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $kit->packs->open($kit->packs->commit($member, 'crew-pack', 'shop:1'), 'mine');

        $this->expectExceptionObject(CollectionRefused::because('The key "shop:1" was opened from another commitment or client seed.'));

        $kit->packs->open($kit->packs->commit($member, 'crew-pack', 'shop:1'), 'mine');
    }

    public function testAKeyOpenedWithAnotherClientSeedIsRefused(): void
    {
        $kit = Kit::standard();
        $pending = $kit->packs->commit(Kit::ref('member:1'), 'crew-pack', 'shop:1');
        $kit->packs->open($pending, 'mine');

        $this->expectExceptionObject(CollectionRefused::because('The key "shop:1" was opened from another commitment or client seed.'));

        $kit->packs->open($pending, 'other words');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedClientSeeds(): iterable
    {
        yield 'none' => [''];
        yield 'too long' => [str_repeat('a', 257)];
        yield 'not text' => ["\xff\xfe"];
    }

    #[DataProvider('refusedClientSeeds')]
    public function testAClientSeedIsOneToTwoHundredFiftySixBytes(string $clientSeed): void
    {
        $kit = Kit::standard();
        self::assertSame(str_repeat('a', 256), $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'booster', 'k1'), str_repeat('a', 256))->clientSeed);

        try {
            $kit->packs->open($kit->packs->commit(Kit::ref('member:2'), 'booster', 'k2'), $clientSeed);
            self::fail('Passed.');
        } catch (InvalidCollection $invalidCollection) {
            self::assertSame('A client seed is 1 to 256 bytes of UTF-8 text.', $invalidCollection->getMessage());
        }

        self::assertSame([], $kit->openings->ofMember(Kit::ref('member:2')));
        self::assertSame([], $kit->collections->copiesOf(Kit::ref('member:2')));
    }

    public function testASeedFromTheSourceIsSixteenToSixtyFourBytes(): void
    {
        $kit = Kit::standard(seeds: new FixedSeeds([str_repeat('a', 64), str_repeat('a', 15), str_repeat('a', 65)]));
        self::assertCount(3, $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'booster', 'k1'), 'mine')->items);

        foreach ([15, 65] as $length) {
            try {
                $kit->packs->commit(Kit::ref('member:1'), 'booster', 'k'.$length);
                self::fail('Passed.');
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame(\sprintf('A seed is 16 to 64 bytes; this one is %d.', $length), $invalidCollection->getMessage());
            }
        }
    }

    public function testAPendingOpeningIsRestoredFromWhatWasKept(): void
    {
        $member = Kit::ref('member:1');
        $pending = new PendingOpening('shop:1', $member, 'booster', str_repeat('ab', 16));

        self::assertSame(hash('sha256', str_repeat("\xab", 16)), $pending->commitment());
        self::assertSame(str_repeat('ab', 64), new PendingOpening('shop:1', $member, 'booster', str_repeat('ab', 64))->seed);

        foreach ([str_repeat('ab', 15), str_repeat('ab', 65), str_repeat('AB', 16), 'abc', str_repeat('ab', 16)."\n"] as $seed) {
            try {
                new PendingOpening('shop:1', $member, 'booster', $seed);
                self::fail($seed);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame('The seed of an opening is 16 to 64 bytes in lower-case hexadecimal.', $invalidCollection->getMessage());
            }
        }
    }

    public function testAnOpeningThatIsNotItsSeedsDrawFailsVerification(): void
    {
        $kit = Kit::standard();
        $opening = $kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'crew-pack', 'k1'), 'mine');
        $other = $opening->items === ['deckhand', 'deckhand'] ? ['pirate', 'pirate'] : ['deckhand', 'deckhand'];
        $b = str_repeat('b', 16);

        self::assertFalse($kit->packs->verify(new PackOpening('k1', $opening->member, 'crew-pack', $opening->seed, $opening->commitment, 'mine', $other, [], $opening->openedAt)));
        self::assertFalse($kit->packs->verify(new PackOpening('k1', $opening->member, 'crew-pack', $opening->seed, hash('sha256', 'other'), 'mine', $opening->items, [], $opening->openedAt)));
        self::assertFalse($kit->packs->verify(new PackOpening('k1', $opening->member, 'crew-pack', bin2hex($b), hash('sha256', $b), 'mine', $opening->items === $this->draw('crew-pack', $b, 'mine', 'k1', 2) ? $other : $opening->items, [], $opening->openedAt)));
        self::assertFalse($kit->packs->verify(new PackOpening('k1', $opening->member, 'crew-pack', $opening->seed, $opening->commitment, 'theirs', $opening->items === $this->draw('crew-pack', str_pad('seed-0', 16, '.'), 'theirs', 'k1', 2) ? $other : $opening->items, [], $opening->openedAt)));
        self::assertFalse($kit->packs->verify(new PackOpening('k2', $opening->member, 'crew-pack', $opening->seed, $opening->commitment, 'mine', $opening->items === $this->draw('crew-pack', str_pad('seed-0', 16, '.'), 'mine', 'k2', 2) ? $other : $opening->items, [], $opening->openedAt)));
    }

    public function testEachSeedFromTheSourceIsUsedOnce(): void
    {
        $kit = Kit::standard();
        $one = $kit->packs->commit(Kit::ref('member:1'), 'booster', 'k1');
        $two = $kit->packs->commit(Kit::ref('member:1'), 'booster', 'k2');

        self::assertSame(bin2hex(str_pad('seed-0', 16, '.')), $one->seed);
        self::assertSame(bin2hex(str_pad('seed-1', 16, '.')), $two->seed);
    }

    public function testSecureSeedsAreThirtyTwoRandomBytes(): void
    {
        $seeds = new SecureSeeds();

        self::assertSame(32, \strlen($seeds->seed()));
        self::assertNotSame($seeds->seed(), $seeds->seed());
    }

    public function testDrawsFollowThePacksWeights(): void
    {
        $kit = Kit::standard();
        $counts = ['deckhand' => 0, 'pirate' => 0, 'captain' => 0];

        for ($i = 0; $i < 400; ++$i) {
            foreach ($kit->packs->open($kit->packs->commit(Kit::ref('member:1'), 'crew-pack', 'k'.$i), 'mine')->items as $item) {
                ++$counts[$item];
            }
        }

        self::assertSame(0, $counts['captain']);
        self::assertEqualsWithDelta(400, $counts['deckhand'], 60);
        self::assertSame(\count(array_filter(array_map(static fn (Copy $copy): bool => 'pirate' === $copy->item, $kit->collections->copiesOf(Kit::ref('member:1'))))), $counts['pirate']);
    }

    /**
     * @return list<string>
     */
    private function draw(string $pack, string $seed, string $clientSeed, string $key, int $size): array
    {
        $catalog = FixedCatalog::fromArray(Kit::definition())->catalog();
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', $clientSeed."\n".$key, $seed, true)));
        $odds = $catalog->odds($catalog->pack($pack)->sets, $catalog->pack($pack)->weights);
        $items = [];

        for ($i = 0; $i < $size; ++$i) {
            $items[] = $odds->draw($randomizer)->code;
        }

        return $items;
    }
}
