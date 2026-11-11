<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Collections\Catalog;
use Tilttek\Collections\CatalogReader;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Item;
use Tilttek\Collections\ItemSet;
use Tilttek\Collections\Odds;
use Tilttek\Collections\Pack;
use Tilttek\Collections\Rarity;
use Tilttek\Collections\Reward;
use Tilttek\Collections\SetKind;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;
use Tilttek\Collections\Texts;

#[CoversClass(Catalog::class)]
#[CoversClass(CatalogReader::class)]
#[CoversClass(FixedCatalog::class)]
#[CoversClass(Odds::class)]
final class CatalogTest extends TestCase
{
    public function testTheReaderBuildsEveryDefinition(): void
    {
        $catalog = FixedCatalog::fromArray(Kit::definition())->catalog();

        self::assertSame(['common', 'rare', 'legendary'], array_map(static fn (Rarity $rarity): string => $rarity->code, $catalog->rarities()));
        self::assertSame(25, $catalog->rarity('rare')->weight);
        self::assertSame(['deckhand', 'pirate', 'captain', 'ember-d6', 'ember-d20', 'frost-d6', 'medal'], array_map(static fn (Item $item): string => $item->code, $catalog->items()));

        $deckhand = $catalog->item('deckhand');
        self::assertSame('Matelot', $deckhand->name->in('fr'));
        self::assertSame('/img/deckhand.webp', $deckhand->image);
        self::assertNull($deckhand->description);
        self::assertNull($deckhand->die);
        self::assertSame('Rules the deck.', $catalog->item('captain')->description?->in('en'));
        self::assertFalse($catalog->item('medal')->tradable);
        self::assertTrue($catalog->item('pirate')->tradable);
        self::assertNull($catalog->item('pirate')->image);

        $die = $catalog->item('ember-d6')->die;
        self::assertSame([6, '#c0392b', 'resin'], [$die?->sides, $die?->color, $die?->material]);
        self::assertSame([20, null, null], [$catalog->item('ember-d20')->die?->sides, $catalog->item('ember-d20')->die?->color, $catalog->item('ember-d20')->die?->material]);

        self::assertSame(['crew', 'ember', 'frost'], array_map(static fn (ItemSet $set): string => $set->code, $catalog->sets()));
        self::assertSame(SetKind::Plain, $catalog->set('crew')->kind);
        self::assertSame(SetKind::Base, $catalog->set('ember')->kind);
        self::assertSame([['kind' => 'points', 'code' => 'xp', 'amount' => 50], ['kind' => 'badge', 'code' => 'full-crew', 'amount' => 1]], array_map(static fn (Reward $reward): array => $reward->toArray(), $catalog->set('crew')->rewards));
        self::assertSame(['deckhand', 'pirate', 'captain'], array_map(static fn (Item $item): string => $item->code, $catalog->itemsOf('crew')));

        self::assertSame(['booster', 'crew-pack'], array_map(static fn (Pack $pack): string => $pack->code, $catalog->packs()));
        self::assertSame(3, $catalog->pack('booster')->size);
        self::assertSame([], $catalog->pack('booster')->sets);
        self::assertSame(['crew'], $catalog->pack('crew-pack')->sets);
        self::assertSame(['common' => 1, 'rare' => 1], $catalog->pack('crew-pack')->weights);
        self::assertSame(1, FixedCatalog::fromArray(['rarities' => ['common' => 1], 'items' => ['a' => ['name' => 'A', 'rarity' => 'common']], 'packs' => ['p' => ['name' => 'P']]])->catalog()->pack('p')->size);
    }

    public function testACatalogMayHoldNothingYet(): void
    {
        self::assertSame([], CatalogReader::read([])->rarities());
        self::assertSame([], new Catalog()->items());

        $catalog = CatalogReader::read(['rarities' => ['common' => 1]]);

        self::assertSame([], $catalog->items());
        self::assertSame([], $catalog->sets());
        self::assertSame([], $catalog->packs());
    }

    public function testWhatIsNotThereIsNotFound(): void
    {
        $catalog = CatalogReader::read(Kit::definition());

        foreach ([['rarity', static fn (): Rarity => $catalog->rarity('mythic')], ['item', static fn (): Item => $catalog->item('parrot')], ['set', static fn (): ItemSet => $catalog->set('fleet')], ['pack', static fn (): Pack => $catalog->pack('chest')], ['set', static fn (): array => $catalog->itemsOf('fleet')]] as [$what, $ask]) {
            try {
                $ask();
                self::fail($what);
            } catch (NotFound $notFound) {
                self::assertStringStartsWith(\sprintf('No %s is named', $what), $notFound->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function wrongCatalogs(): iterable
    {
        $rarities = ['common' => 1];

        yield 'an unknown key' => [['rarities' => $rarities, 'cards' => []], 'Unknown key "cards" in the catalog; the keys are rarities, items, sets, packs.'];
        yield 'rarities not a map' => [['rarities' => 'common'], 'The rarities is a map of values.'];
        yield 'a rarity without weight' => [['rarities' => ['common' => []]], 'The rarity "common" has a whole weight.'];
        yield 'a rarity of text' => [['rarities' => ['common' => '3']], 'The rarity "common" has a whole weight.'];
        yield 'a rarity with an unknown key' => [['rarities' => ['common' => ['weight' => 1, 'colour' => 'grey']]], 'Unknown key "colour" in the rarity "common"; the keys are weight.'];
        yield 'an item not a map' => [['rarities' => $rarities, 'items' => ['a' => 'A']], 'The item "a" is a map of values.'];
        yield 'an item with an unknown key' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'colour' => 'red']]], 'Unknown key "colour" in the item "a"; the keys are name, rarity, sets, description, image, tradable, die.'];
        yield 'an item without name' => [['rarities' => $rarities, 'items' => ['a' => ['rarity' => 'common']]], 'The name of "a" is a text, or texts by locale.'];
        yield 'an item without rarity' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A']]], 'The rarity of "a" is a text.'];
        yield 'an item of an unknown rarity' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'rare']]], 'No rarity is named "rare".'];
        yield 'an item of an unknown set' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'sets' => ['crew']]]], 'No set is named "crew".'];
        yield 'sets not a list' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'sets' => ['x' => 'crew']]]], 'The sets of "a" are a list of codes.'];
        yield 'sets a text' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'sets' => 'crew']]], 'The sets of "a" are a list of codes.'];
        yield 'a set not a code' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'sets' => [3]]]], 'Each of the sets of "a" is a text.'];
        yield 'a description of a number' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'description' => 3]]], 'The description of "a" is a text, or texts by locale.'];
        yield 'an image of a number' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'image' => 3]]], 'The image of "a" is a text.'];
        yield 'tradable in words' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'tradable' => 'no']]], 'Whether "a" changes hands is true or false.'];
        yield 'a die not a map' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'die' => 6]]], 'The die of "a" is a map of values.'];
        yield 'a die with an unknown key' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'die' => ['sides' => 6, 'faces' => []]]]], 'Unknown key "faces" in the die of "a"; the keys are sides, color, material.'];
        yield 'a die without sides' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'die' => ['color' => '#ffffff']]]], 'The die of "a" has a whole number of sides.'];
        yield 'a die of a numbered colour' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'die' => ['sides' => 6, 'color' => 3]]]], 'The colour of the die of "a" is a text.'];
        yield 'a die of a numbered material' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common', 'die' => ['sides' => 6, 'material' => 3]]]], 'The material of the die of "a" is a text.'];
        yield 'a set not a map' => [['rarities' => $rarities, 'sets' => ['crew' => 'Crew']], 'The set "crew" is a map of values.'];
        yield 'a set with an unknown key' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'size' => 3]]], 'Unknown key "size" in the set "crew"; the keys are name, kind, rewards.'];
        yield 'a set of an unknown kind' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'kind' => 'bought']]], 'The kind of "crew" is "plain", "base", "earned" or "paid".'];
        yield 'a set of a numbered kind' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'kind' => 1]]], 'The kind of "crew" is a text.'];
        yield 'rewards not a list' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => ['a' => []]]]], 'The rewards of "crew" are a list.'];
        yield 'rewards a text' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => 'xp']]], 'The rewards of "crew" are a list.'];
        yield 'a reward not a map' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => ['xp']]]], 'A reward of "crew" is a map of values.'];
        yield 'a reward with an unknown key' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'to' => 'all']]]]], 'Unknown key "to" in a reward of "crew"; the keys are kind, code, amount.'];
        yield 'a reward without kind' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => [['code' => 'xp']]]]], 'The kind of a reward of "crew" is a text.'];
        yield 'a reward without code' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => [['kind' => 'points']]]]], 'What a reward of "crew" names is a text.'];
        yield 'a reward of a text amount' => [['rarities' => $rarities, 'sets' => ['crew' => ['name' => 'Crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => '5']]]]], 'A reward of "crew" gives a whole amount.'];
        yield 'a pack not a map' => [['rarities' => $rarities, 'packs' => ['p' => 'P']], 'The pack "p" is a map of values.'];
        yield 'a pack with an unknown key' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P', 'odds' => []]]], 'Unknown key "odds" in the pack "p"; the keys are name, size, sets, weights.'];
        yield 'a pack of a text size' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P', 'size' => '3']]], 'The size of "p" is a whole number.'];
        yield 'a pack of text weights' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P', 'weights' => ['common' => '3']]]], 'The weights of "p" are whole numbers by rarity.'];
        yield 'weights by number' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P', 'weights' => [1 => 3]]]], 'The weights of "p" are whole numbers by rarity.'];
        yield 'weights a list' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P', 'weights' => 3]]], 'The weights of "p" is a map of values.'];
        yield 'a pack with nothing to draw' => [['rarities' => $rarities, 'packs' => ['p' => ['name' => 'P']]], 'A draw has at least one item of a rarity it gives a weight to.'];
        yield 'a pack of an unknown set' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common']], 'packs' => ['p' => ['name' => 'P', 'sets' => ['crew']]]], 'No set is named "crew".'];
        yield 'a pack of an unknown rarity' => [['rarities' => $rarities, 'items' => ['a' => ['name' => 'A', 'rarity' => 'common']], 'packs' => ['p' => ['name' => 'P', 'weights' => ['common' => 1, 'rare' => 1]]]], 'No rarity is named "rare".'];
        yield 'a pack weighting nothing it holds' => [['rarities' => ['common' => 1, 'rare' => 1], 'items' => ['a' => ['name' => 'A', 'rarity' => 'common']], 'packs' => ['p' => ['name' => 'P', 'weights' => ['rare' => 1]]]], 'A draw has at least one item of a rarity it gives a weight to.'];
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    #[DataProvider('wrongCatalogs')]
    public function testAWrongCatalogIsRefused(array $definition, string $message): void
    {
        try {
            CatalogReader::read($definition);
            self::fail('Passed.');
        } catch (InvalidCollection|NotFound $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }

    public function testTwoDefinitionsOfAKindCannotShareACode(): void
    {
        $rarity = new Rarity('common', 1);
        $item = new Item('a', Texts::of('A'), 'common', ['s']);
        $set = new ItemSet('s', Texts::of('S'));
        $pack = new Pack('p', Texts::of('P'));

        foreach ([[[], [], [], 'rarity', 'common'], [[$item, $item], [$set], [], 'item', 'a'], [[$item], [$set, $set], [], 'set', 's'], [[$item], [$set], [$pack, $pack], 'pack', 'p']] as [$items, $sets, $packs, $what, $code]) {
            try {
                new Catalog('rarity' === $what ? [$rarity, $rarity] : [$rarity], $items, $sets, $packs);
                self::fail($what);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame(\sprintf('Two of the catalog\'s %s definitions are named "%s".', $what, $code), $invalidCollection->getMessage());
            }
        }
    }

    public function testOddsArePublishedForEachRarityAndItem(): void
    {
        $catalog = CatalogReader::read(Kit::definition());
        $odds = $catalog->odds();

        self::assertSame(['common' => 0.7, 'rare' => 0.25, 'legendary' => 0.05], $odds->rarities());
        self::assertEqualsWithDelta(['deckhand' => 0.7 / 3, 'ember-d6' => 0.7 / 3, 'ember-d20' => 0.7 / 3, 'pirate' => 0.25 / 3, 'frost-d6' => 0.25 / 3, 'medal' => 0.25 / 3, 'captain' => 0.05], $odds->itemOdds(), 1e-12);
        self::assertSame(['common' => 0.5, 'rare' => 0.5], $catalog->odds(['crew'], ['common' => 1, 'rare' => 1])->rarities());
        self::assertSame(['deckhand' => 0.5, 'pirate' => 0.5], $catalog->odds(['crew'], ['common' => 1, 'rare' => 1])->itemOdds());
        self::assertSame(['deckhand', 'pirate', 'captain'], array_keys($catalog->odds(['crew', 'crew'])->itemOdds()));
        self::assertSame(['rare' => 0.5, 'common' => 0.5], $catalog->odds(['crew', 'ember'], ['rare' => 1, 'common' => 1])->rarities());
        self::assertSame(['pirate', 'deckhand', 'ember-d6', 'ember-d20'], array_keys($catalog->odds(['crew', 'ember'], ['rare' => 1, 'common' => 1])->itemOdds()));
    }

    public function testADrawLandsAsTheOddsSay(): void
    {
        $catalog = CatalogReader::read(Kit::definition());
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', 'draws', true)));
        $odds = $catalog->odds(['crew']);
        $counts = ['deckhand' => 0, 'pirate' => 0, 'captain' => 0];

        for ($i = 0; $i < 10_000; ++$i) {
            ++$counts[$odds->draw($randomizer)->code];
        }

        self::assertEqualsWithDelta(7000, $counts['deckhand'], 250);
        self::assertEqualsWithDelta(2500, $counts['pirate'], 250);
        self::assertEqualsWithDelta(500, $counts['captain'], 120);

        $even = $catalog->odds(['ember', 'frost'], ['common' => 1, 'rare' => 1]);
        $counts = ['ember-d6' => 0, 'ember-d20' => 0, 'frost-d6' => 0];

        for ($i = 0; $i < 4000; ++$i) {
            ++$counts[$even->draw($randomizer)->code];
        }

        self::assertEqualsWithDelta(1000, $counts['ember-d6'], 120);
        self::assertEqualsWithDelta(1000, $counts['ember-d20'], 120);
        self::assertEqualsWithDelta(2000, $counts['frost-d6'], 150);
    }

    public function testADrawOfASingleItemAlwaysLandsOnIt(): void
    {
        $odds = new Odds([new Item('a', Texts::of('A'), 'common')], ['common' => 1, 'rare' => 4]);

        self::assertSame(['common' => 1], $odds->weights);
        self::assertSame(['common'], array_keys(new Odds([new Item('a', Texts::of('A'), 'common'), new Item('b', Texts::of('B'), 'rare')], ['common' => 1])->items));
        self::assertSame('a', $odds->draw(new Randomizer())->code);
    }
}
