<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Reading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Reading\CatalogReader;

#[CoversClass(CatalogReader::class)]
final class CatalogReaderTest extends TestCase
{
    public function testACatalogue(): void
    {
        $catalog = new CatalogReader()->read([
            'tables' => [
                'headgear' => ['name' => 'Headgear', 'entries' => [
                    ['key' => 'helmet', 'name' => 'Helmet', 'image' => '/loot/helmet.png', 'slot' => 'head'],
                    ['key' => 'hood', 'name' => 'Hood'],
                ]],
                7 => ['name' => 'Seven', 'entries' => [['key' => 'a', 'name' => 'A'], ['key' => 'b', 'name' => 'B']]],
            ],
            'boxes' => [
                'starter' => ['name' => 'Starter box', 'tables' => ['headgear', 'headgear', '7']],
                3 => ['name' => 'Three', 'tables' => ['7']],
            ],
        ]);

        $headgear = $catalog->table('headgear');
        self::assertSame('Headgear', $headgear->name);
        self::assertSame('/loot/helmet.png', $headgear->entry(1)->image);
        self::assertSame('head', $headgear->entry(1)->slot);
        self::assertNull($headgear->entry(2)->image);
        self::assertNull($headgear->entry(2)->slot);
        self::assertSame('7', $catalog->table('7')->key);
        self::assertSame([2, 2, 2], $catalog->box('starter')->dice());
        self::assertSame('Starter box', $catalog->box('starter')->name);
        self::assertSame('3', $catalog->box('3')->key);
    }

    public function testAnEmptyCatalogue(): void
    {
        $catalog = new CatalogReader()->read([]);

        self::assertSame([], $catalog->tables());
        self::assertSame([], $catalog->boxes());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $table = ['name' => 'Rings', 'entries' => [['key' => 'a', 'name' => 'A'], ['key' => 'b', 'name' => 'B']]];

        yield 'tables that are no map' => [['tables' => 'rings'], 'tables is a map.'];
        yield 'a table that is no map' => [['tables' => ['rings' => 'rings']], 'tables.rings is a map.'];
        yield 'a table without entries' => [['tables' => ['rings' => ['name' => 'Rings']]], 'tables.rings.entries is a list.'];
        yield 'entries that are no list' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => ['a' => []]]]], 'tables.rings.entries is a list.'];
        yield 'an entry that is no map' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => ['a']]]], 'tables.rings.entries.0 is a map.'];
        yield 'an entry without a key' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['name' => 'A']]]]], 'tables.rings.entries.0.key is a text.'];
        yield 'an entry without a name' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => 'a']]]]], 'tables.rings.entries.0.name is a text.'];
        yield 'an image that is no text' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => 'a', 'name' => 'A', 'image' => 3]]]]], 'tables.rings.entries.0.image is a text.'];
        yield 'a slot that is no text' => [['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => 'a', 'name' => 'A', 'slot' => true]]]]], 'tables.rings.entries.0.slot is a text.'];
        yield 'a table without a name' => [['tables' => ['rings' => ['entries' => $table['entries']]]], 'tables.rings.name is a text.'];
        yield 'boxes that are no map' => [['tables' => ['rings' => $table], 'boxes' => 'box'], 'boxes is a map.'];
        yield 'a box that is no map' => [['boxes' => ['box' => 1]], 'boxes.box is a map.'];
        yield 'a box without tables' => [['boxes' => ['box' => ['name' => 'Box']]], 'boxes.box.tables is a list.'];
        yield 'a table of a box that is no text' => [['tables' => ['rings' => $table], 'boxes' => ['box' => ['name' => 'Box', 'tables' => [1]]]], 'boxes.box.tables.0 is a text.'];
        yield 'a table the catalogue does not declare' => [['tables' => ['rings' => $table], 'boxes' => ['box' => ['name' => 'Box', 'tables' => ['hats']]]], 'The box box rolls the table hats, which the catalogue does not declare.'];
        yield 'a box without a name' => [['tables' => ['rings' => $table], 'boxes' => ['box' => ['tables' => ['rings']]]], 'boxes.box.name is a text.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('refused')]
    public function testACatalogueThatDoesNotRead(array $data, string $message): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CatalogReader()->read($data);
    }
}
