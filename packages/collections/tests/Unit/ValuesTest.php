<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Album;
use Tilttek\Collections\CollectibleDie;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Daily\Days;
use Tilttek\Collections\Daily\FixedDate;
use Tilttek\Collections\Daily\PickOrigin;
use Tilttek\Collections\DefinitionKind;
use Tilttek\Collections\Dice\DieChoice;
use Tilttek\Collections\Erasure;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Holding;
use Tilttek\Collections\Item;
use Tilttek\Collections\ItemSet;
use Tilttek\Collections\Names;
use Tilttek\Collections\Pack;
use Tilttek\Collections\Pack\PackOpening;
use Tilttek\Collections\Rarity;
use Tilttek\Collections\Reward;
use Tilttek\Collections\SetCompletion;
use Tilttek\Collections\SetKind;
use Tilttek\Collections\SetProgress;
use Tilttek\Collections\Source;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;
use Tilttek\Collections\Texts;

#[CoversClass(Names::class)]
#[CoversClass(Texts::class)]
#[CoversClass(Rarity::class)]
#[CoversClass(CollectibleDie::class)]
#[CoversClass(Reward::class)]
#[CoversClass(Item::class)]
#[CoversClass(ItemSet::class)]
#[CoversClass(Pack::class)]
#[CoversClass(CollectionKey::class)]
#[CoversClass(Copy::class)]
#[CoversClass(SetCompletion::class)]
#[CoversClass(PackOpening::class)]
#[CoversClass(DailyPick::class)]
#[CoversClass(Days::class)]
#[CoversClass(FixedDate::class)]
#[CoversClass(DieChoice::class)]
#[CoversClass(DefinitionKind::class)]
#[CoversClass(Holding::class)]
#[CoversClass(SetProgress::class)]
#[CoversClass(Album::class)]
#[CoversClass(InvalidCollection::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(CollectionRefused::class)]
final class ValuesTest extends TestCase
{
    public function testACodeIsLowerCaseLettersDigitsAndAFewSigns(): void
    {
        self::assertSame('dice.ember-2_b', Names::check('dice.ember-2_b', 'item'));
        self::assertSame('a'.str_repeat('b', 63), Names::check('a'.str_repeat('b', 63), 'item'));

        foreach (['', 'Pirate', '2d6', 'a'.str_repeat('b', 64), 'a b', "a\n", 'x'."\u{e9}"] as $wrong) {
            try {
                Names::check($wrong, 'item');
                self::fail(\sprintf('"%s" passed.', $wrong));
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame(\sprintf('"%s" is not a item code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $wrong), $invalidCollection->getMessage());
            }
        }
    }

    public function testATextIsReadInTheReadersLocaleWithFallbacks(): void
    {
        $texts = Texts::of(['fr' => 'Matelot', 'en' => 'Deckhand', 'fr_CA' => 'Moussaillon']);

        self::assertSame('Moussaillon', $texts->in('fr_CA'));
        self::assertSame('Matelot', $texts->in('fr_BE'));
        self::assertSame('Matelot', $texts->in('fr'));
        self::assertSame('Deckhand', $texts->in('de'));
        self::assertSame('Erste', Texts::of(['de' => 'Erste', 'es' => 'Primera'])->in('it'));
        self::assertSame(['en' => 'Pirate'], Texts::of('Pirate')->texts);
        self::assertSame(['es' => str_repeat("\u{e9}", Texts::MAX_LENGTH)], Texts::of(['es' => str_repeat("\u{e9}", Texts::MAX_LENGTH)])->texts);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>|string, string}>
     */
    public static function wrongTexts(): iterable
    {
        yield 'none' => [[], 'A text is written in at least one language.'];
        yield 'a list' => [['Pirate'], '"0" is not a locale, such as "en", "fr" or "fr_CA".'];
        yield 'a region alone' => [['CA' => 'x'], '"CA" is not a locale, such as "en", "fr" or "fr_CA".'];
        yield 'a prefix' => [['_fr' => 'x'], '"_fr" is not a locale, such as "en", "fr" or "fr_CA".'];
        yield 'a suffix' => [['fr_CAx' => 'x'], '"fr_CAx" is not a locale, such as "en", "fr" or "fr_CA".'];
        yield 'a new line' => [["en\n" => 'x'], "\"en\n\" is not a locale, such as \"en\", \"fr\" or \"fr_CA\"."];
        yield 'blank' => [' ', 'The text in "en" is 1 to 2000 characters.'];
        yield 'not a text' => [['en' => 3], 'The text in "en" is 1 to 2000 characters.'];
        yield 'too long' => [str_repeat('a', 2001), 'The text in "en" is 1 to 2000 characters.'];
    }

    /**
     * @param array<array-key, mixed>|string $texts
     */
    #[DataProvider('wrongTexts')]
    public function testAWrongTextIsRefused(array|string $texts, string $message): void
    {
        $this->expectExceptionObject(InvalidCollection::because($message));

        Texts::of($texts);
    }

    public function testARarityWeighsOneToAMillion(): void
    {
        self::assertSame(1, new Rarity('common', 1)->weight);
        self::assertSame(1_000_000, new Rarity('common', 1_000_000)->weight);

        foreach ([0, 1_000_001] as $weight) {
            try {
                new Rarity('common', $weight);
                self::fail('Passed.');
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame(\sprintf('The weight of a rarity is 1 to 1000000; "common" weighs %d.', $weight), $invalidCollection->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidCollection::name('rarity', 'Rare'));
        new Rarity('Rare', 1);
    }

    public function testADieHasSidesAColourAndAMaterial(): void
    {
        $die = new CollectibleDie(2, '#A0b1C2', str_repeat("\u{e9}", 64));
        self::assertSame([2, '#A0b1C2'], [$die->sides, $die->color]);
        self::assertSame(1000, new CollectibleDie(1000)->sides);
        self::assertNull(new CollectibleDie(6)->material);

        foreach ([[1, null, null, 'A die has 2 to 1000 sides; this one has 1.'], [1001, null, null, 'A die has 2 to 1000 sides; this one has 1001.'], [6, 'red', null, 'The colour of a die is written #rrggbb; "red" is not.'], [6, 'x#a0b1c2', null, 'The colour of a die is written #rrggbb; "x#a0b1c2" is not.'], [6, '#a0b1c2x', null, 'The colour of a die is written #rrggbb; "#a0b1c2x" is not.'], [6, "#a0b1c2\n", null, "The colour of a die is written #rrggbb; \"#a0b1c2\n\" is not."], [6, null, ' ', 'The material of a die is 1 to 64 characters.'], [6, null, str_repeat('a', 65), 'The material of a die is 1 to 64 characters.']] as [$sides, $color, $material, $message]) {
            try {
                new CollectibleDie($sides, $color, $material);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame($message, $invalidCollection->getMessage());
            }
        }
    }

    public function testARewardIsDeclared(): void
    {
        self::assertSame(['kind' => 'points', 'code' => 'xp', 'amount' => 1], new Reward('points', 'xp')->toArray());
        self::assertSame(1_000_000_000, new Reward('points', str_repeat('x', 255), 1_000_000_000)->amount);

        foreach ([['points', '', 1, 'What a reward names is 1 to 255 bytes without whitespace or control characters; "" is not.'], ['points', str_repeat('x', 256), 1, \sprintf('What a reward names is 1 to 255 bytes without whitespace or control characters; "%s" is not.', str_repeat('x', 256))], ['points', 'a b', 1, 'What a reward names is 1 to 255 bytes without whitespace or control characters; "a b" is not.'], ['points', 'xp', 0, 'A reward gives 1 to 1000000000; this one gives 0.'], ['points', 'xp', 1_000_000_001, 'A reward gives 1 to 1000000000; this one gives 1000000001.']] as [$kind, $code, $amount, $message]) {
            try {
                new Reward($kind, $code, $amount);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame($message, $invalidCollection->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidCollection::name('reward kind', 'Points'));
        new Reward('Points', 'xp');
    }

    public function testAnItemBelongsToSetsAndHasAnImage(): void
    {
        $item = new Item('pirate', Texts::of('Pirate'), 'rare', ['crew', 'ship'], image: str_repeat('a', 2048));

        self::assertTrue($item->belongsTo('ship'));
        self::assertFalse($item->belongsTo('fleet'));
        self::assertTrue($item->tradable);
        self::assertCount(20, new Item('a', Texts::of('A'), 'common', array_map(static fn (int $i): string => 's'.$i, range(1, 20)))->sets);

        foreach ([
            [['crew', 'crew'], null, '"pirate" belongs to at most 20 sets, each named once.'],
            [array_map(static fn (int $i): string => 's'.$i, range(1, 21)), null, '"pirate" belongs to at most 20 sets, each named once.'],
            [[], '', 'The image of "pirate" is a path or a URL of 1 to 2048 bytes without whitespace.'],
            [[], str_repeat('a', 2049), 'The image of "pirate" is a path or a URL of 1 to 2048 bytes without whitespace.'],
            [[], '/a b.png', 'The image of "pirate" is a path or a URL of 1 to 2048 bytes without whitespace.'],
        ] as [$sets, $image, $message]) {
            try {
                new Item('pirate', Texts::of('Pirate'), 'rare', $sets, image: $image);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame($message, $invalidCollection->getMessage());
            }
        }

        foreach ([['Pirate', 'rare', [], 'item'], ['pirate', 'Rare', [], 'rarity'], ['pirate', 'rare', ['Crew'], 'set']] as [$code, $rarity, $sets, $what]) {
            try {
                new Item($code, Texts::of('Pirate'), $rarity, $sets);
                self::fail($what);
            } catch (InvalidCollection $invalidCollection) {
                self::assertStringContainsString(\sprintf('is not a %s code', $what), $invalidCollection->getMessage());
            }
        }
    }

    public function testASetDeclaresAtMostTwentyRewards(): void
    {
        $rewards = array_fill(0, 20, new Reward('points', 'xp'));
        self::assertCount(20, new ItemSet('crew', Texts::of('Crew'), SetKind::Paid, $rewards)->rewards);
        self::assertSame(SetKind::Plain, new ItemSet('crew', Texts::of('Crew'))->kind);

        try {
            new ItemSet('crew', Texts::of('Crew'), rewards: [...$rewards, new Reward('points', 'xp')]);
            self::fail('Passed.');
        } catch (InvalidCollection $invalidCollection) {
            self::assertSame('The set "crew" declares at most 20 rewards.', $invalidCollection->getMessage());
        }

        $this->expectExceptionObject(InvalidCollection::name('set', 'Crew'));
        new ItemSet('Crew', Texts::of('Crew'));
    }

    public function testAPackHoldsOneToFiftyItems(): void
    {
        self::assertSame(1, new Pack('p', Texts::of('P'))->size);
        self::assertSame(50, new Pack('p', Texts::of('P'), 50, ['crew'], ['rare' => 3])->size);

        foreach ([[0, [], [], 'A pack holds 1 to 50 items; "p" holds 0.'], [51, [], [], 'A pack holds 1 to 50 items; "p" holds 51.'], [1, ['Crew'], [], '"Crew" is not a set code'], [1, [], ['rare' => 0], 'The weight of a rarity is 1 to 1000000; "rare" weighs 0.'], [1, [], ['Rare' => 1], '"Rare" is not a rarity code']] as [$size, $sets, $weights, $message]) {
            try {
                new Pack('p', Texts::of('P'), $size, $sets, $weights);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertStringStartsWith($message, $invalidCollection->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidCollection::name('pack', 'P'));
        new Pack('P', Texts::of('P'));
    }

    public function testAKeyIsTheApplicationsOrDerived(): void
    {
        $key = CollectionKey::fromString('order:12:line:2');

        self::assertSame('order:12:line:2#3', $key->derive('3')->value);
        self::assertSame('#daily/2026-11-07', CollectionKey::internal('daily/2026-11-07')->value);
        self::assertSame(str_repeat('k', 200), CollectionKey::fromString(str_repeat('k', 200))->value);

        foreach (['' => 'A collection key is 1 to 200 bytes; this one is 0.', str_repeat('k', 201) => 'A collection key is 1 to 200 bytes; this one is 201.', 'a b' => 'A collection key cannot contain whitespace, control characters or "#"; "a b" does.', "a\x7F" => "A collection key cannot contain whitespace, control characters or \"#\"; \"a\x7F\" does.", 'a#1' => 'A collection key cannot contain whitespace, control characters or "#"; "a#1" does.'] as $wrong => $message) {
            try {
                CollectionKey::fromString($wrong);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame($message, $invalidCollection->getMessage());
            }
        }
    }

    public function testACopyIsKnownByTheKeyItWasGivenUnder(): void
    {
        $id = Copy::idFor(CollectionKey::fromString('order:12'));
        self::assertSame(substr(hash('sha256', 'order:12'), 0, 32), $id);

        $copy = new Copy($id, 'pirate', Kit::ref('member:1'), Source::Purchase, 'order:12', new DateTimeImmutable('2026-11-07 12:00:00', new DateTimeZone('America/Toronto')));
        self::assertSame('collection-copy:'.$id, $copy->reference()->toString());
        self::assertTrue($copy->tradable);
        self::assertFalse($copy->moved);

        $moved = $copy->movedTo(Kit::ref('member:2'));
        self::assertSame(['id' => $id, 'item' => 'pirate', 'owner' => 'member:2', 'source' => 'purchase', 'key' => 'order:12', 'acquired_at' => '2026-11-07T12:00:00-05:00', 'tradable' => true, 'moved' => true], $moved->toArray());
        self::assertFalse(new Copy($id, 'medal', Kit::ref('member:1'), Source::Reward, 'k', new DateTimeImmutable(), false)->movedTo(Kit::ref('member:2'))->tradable);
    }

    public function testRecordsWriteThemselvesOut(): void
    {
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');

        self::assertSame(['set' => 'crew', 'member' => 'member:1', 'completed_at' => '2026-11-07T12:00:00+00:00'], new SetCompletion('crew', Kit::ref('member:1'), $at)->toArray());
        self::assertSame(['member' => 'member:1', 'sides' => 6, 'copy' => 'c1'], new DieChoice(Kit::ref('member:1'), 6, 'c1')->toArray());

        $opening = new PackOpening('k', Kit::ref('member:1'), 'booster', str_repeat('ab', 16), str_repeat('0', 64), 'mine', ['pirate'], ['c1'], $at);
        self::assertSame(['key' => 'k', 'member' => 'member:1', 'pack' => 'booster', 'seed' => str_repeat('ab', 16), 'commitment' => str_repeat('0', 64), 'client_seed' => 'mine', 'items' => ['pirate'], 'copies' => ['c1'], 'opened_at' => '2026-11-07T12:00:00+00:00'], $opening->toArray());
        self::assertSame(str_repeat('ab', 64), new PackOpening('k', Kit::ref('member:1'), 'booster', str_repeat('ab', 64), str_repeat('f', 64), 'mine', [], [], $at)->seed);

        foreach ([[str_repeat('ab', 15), str_repeat('0', 64)], [str_repeat('ab', 65), str_repeat('0', 64)], [str_repeat('AB', 16), str_repeat('0', 64)], ['x'.str_repeat('ab', 16), str_repeat('0', 64)], [str_repeat('ab', 16).'x', str_repeat('0', 64)], [str_repeat('ab', 16).'a', str_repeat('0', 64)], [str_repeat('ab', 16)."\n", str_repeat('0', 64)]] as [$seed, $commitment]) {
            try {
                new PackOpening('k', Kit::ref('member:1'), 'booster', $seed, $commitment, 'mine', [], [], $at);
                self::fail($seed);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame('The seed of an opening is 16 to 64 bytes in lower-case hexadecimal.', $invalidCollection->getMessage());
            }
        }

        foreach ([str_repeat('0', 63), 'x'.str_repeat('0', 64), str_repeat('0', 64).'x', str_repeat('A', 64), str_repeat('0', 64)."\n"] as $commitment) {
            try {
                new PackOpening('k', Kit::ref('member:1'), 'booster', str_repeat('ab', 16), $commitment, 'mine', [], [], $at);
                self::fail($commitment);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame('The commitment of an opening is a SHA-256 in lower-case hexadecimal.', $invalidCollection->getMessage());
            }
        }

        foreach (['', str_repeat('a', 257), "\xff"] as $clientSeed) {
            try {
                new PackOpening('k', Kit::ref('member:1'), 'booster', str_repeat('ab', 16), str_repeat('0', 64), $clientSeed, [], [], $at);
                self::fail($clientSeed);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame('A client seed is 1 to 256 bytes of UTF-8 text.', $invalidCollection->getMessage());
            }
        }

        $erasure = new Erasure(1, 2, 3, 4);
        self::assertSame([1, 2, 3, 4], [$erasure->copies, $erasure->completions, $erasure->openings, $erasure->choices]);
    }

    public function testDaysAreWrittenYearMonthDay(): void
    {
        self::assertTrue(Days::isDay('2028-02-29'));
        self::assertFalse(Days::isDay('2027-02-29'));
        self::assertFalse(Days::isDay('2027-2-28'));
        self::assertFalse(Days::isDay('Christmas'));
        self::assertSame('2026-11-07T00:00:00-05:00', Days::midnight('2026-11-07', new DateTimeZone('America/Toronto'))->format('c'));

        $this->expectExceptionObject(InvalidCollection::because('"2026-13-01" is not a day written Y-m-d.'));
        Days::midnight('2026-13-01', new DateTimeZone('UTC'));
    }

    public function testAFixedDateComesBackEachYearOrOnce(): void
    {
        $halloween = new FixedDate('10-31', 'pumpkin');
        $once = new FixedDate('2026-12-25', 'star');
        $day = static fn (string $day): DateTimeImmutable => new DateTimeImmutable($day);

        self::assertSame('pumpkin', $halloween->itemOn($day('2027-10-31')));
        self::assertNull($halloween->itemOn($day('2027-10-30')));
        self::assertSame('star', $once->itemOn($day('2026-12-25')));
        self::assertNull($once->itemOn($day('2027-12-25')));
        self::assertSame('leap', new FixedDate('02-29', 'leap')->itemOn($day('2028-02-29')));

        foreach (['13-01', '2026-02-30', 'xmas', '1031'] as $wrong) {
            try {
                new FixedDate($wrong, 'pumpkin');
                self::fail($wrong);
            } catch (InvalidCollection $invalidCollection) {
                self::assertSame(\sprintf('"%s" is not a date: a month and a day, such as "10-31", or a full date, such as "2026-12-25".', $wrong), $invalidCollection->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidCollection::name('item', 'Pumpkin'));
        new FixedDate('10-31', 'Pumpkin');
    }

    public function testAPickIsADayAndAnItem(): void
    {
        self::assertSame(PickOrigin::Drawn, new DailyPick('2026-11-07', 'pirate', PickOrigin::Drawn)->origin);

        try {
            new DailyPick('2026-11-31', 'pirate', PickOrigin::Drawn);
            self::fail('Passed.');
        } catch (InvalidCollection $invalidCollection) {
            self::assertSame('"2026-11-31" is not a day written Y-m-d.', $invalidCollection->getMessage());
        }

        $this->expectExceptionObject(InvalidCollection::name('item', 'Pirate'));
        new DailyPick('2026-11-07', 'Pirate', PickOrigin::Drawn);
    }

    public function testEachKindOfDefinitionHasItsKey(): void
    {
        self::assertSame(['rarities', 'items', 'sets', 'packs'], array_map(static fn (DefinitionKind $kind): string => $kind->key(), DefinitionKind::cases()));
    }

    public function testAnAlbumShowsHoldingsDoublesAndSets(): void
    {
        $pirate = new Item('pirate', Texts::of('Pirate'), 'rare', ['crew']);
        $captain = new Item('captain', Texts::of('Captain'), 'rare', ['crew']);
        $parrot = new Item('parrot', Texts::of('Parrot'), 'rare');
        $crew = new SetProgress(new ItemSet('crew', Texts::of('Crew')), [$pirate], [$captain], true);
        $album = new Album([new Holding($captain, 0), new Holding($pirate, 3), new Holding($parrot, 1)], [$crew]);

        self::assertSame(2, $album->holdings[1]->doubles());
        self::assertSame(['pirate', 'parrot'], array_map(static fn (Holding $holding): string => $holding->item->code, $album->owned()));
        self::assertSame(['pirate'], array_map(static fn (Holding $holding): string => $holding->item->code, $album->doubles()));
        self::assertSame($crew, $album->set('crew'));
        self::assertNull($album->set('fleet'));
        self::assertSame(2, $crew->total());
        self::assertFalse($crew->isComplete());
        self::assertTrue(new SetProgress($crew->set, [$pirate], [], false)->isComplete());
        self::assertFalse(new SetProgress($crew->set, [], [], false)->isComplete());
    }

    public function testExceptionsSayWhatIsMissingOrRefused(): void
    {
        self::assertSame('No item is named "parrot".', NotFound::of('item', 'parrot')->getMessage());
        self::assertSame('Nope.', CollectionRefused::because('Nope.')->getMessage());
    }
}
