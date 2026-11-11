<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootEntry;

#[CoversClass(LootEntry::class)]
#[CoversClass(InvalidLoot::class)]
final class LootEntryTest extends TestCase
{
    public function testAnEntryHasAKeyANameAnImageAndASlot(): void
    {
        $entry = new LootEntry('sword', 'Sword', '/loot/sword.png', 'right-hand');

        self::assertSame('sword', $entry->key);
        self::assertSame('Sword', $entry->name);
        self::assertSame('/loot/sword.png', $entry->image);
        self::assertSame('right-hand', $entry->slot);
    }

    public function testTheImageAndTheSlotAreOptional(): void
    {
        $entry = new LootEntry('1', 'Rest in peace, brave goblin');

        self::assertNull($entry->image);
        self::assertNull($entry->slot);
    }

    public function testTheLimitsAreAllowed(): void
    {
        $entry = new LootEntry(str_repeat('k', 64), str_repeat('é', 1000), str_repeat('i', 2048), str_repeat('s', 64));

        self::assertSame(1000, mb_strlen($entry->name));
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string, string}>
     */
    public static function refused(): iterable
    {
        yield 'a key in capitals' => ['Sword', 'Sword', null, null, '"Sword" is not the key of an entry: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'an empty key' => ['', 'Sword', null, null, '"" is not the key of an entry'];
        yield 'a key starting with a dash' => ['-sword', 'Sword', null, null, 'is not the key of an entry'];
        yield 'a key too long' => [str_repeat('k', 65), 'Sword', null, null, 'is not the key of an entry'];
        yield 'a key ending with a line' => ["sword\n", 'Sword', null, null, 'is not the key of an entry'];
        yield 'a blank name' => ['sword', ' ', null, null, 'The name of the entry sword is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'a name too long' => ['sword', str_repeat('é', 1001), null, null, 'The name of the entry sword is 1 to 1000'];
        yield 'a name not UTF-8' => ['sword', "\xFF", null, null, 'The name of the entry sword is'];
        yield 'an image with a space' => ['sword', 'Sword', '/loot/a sword.png', null, 'The image of the entry sword is not a path or URL: 1 to 2048 visible ASCII characters.'];
        yield 'an empty image' => ['sword', 'Sword', '', null, 'The image of the entry sword'];
        yield 'an image too long' => ['sword', 'Sword', str_repeat('i', 2049), null, 'The image of the entry sword'];
        yield 'an image ending with a line' => ['sword', 'Sword', "/sword.png\n", null, 'The image of the entry sword'];
        yield 'a slot in capitals' => ['sword', 'Sword', null, 'Hand', '"Hand" is not the slot of the entry sword: 1 to 64 lowercase letters, digits, "_" or "-".'];
    }

    #[DataProvider('refused')]
    public function testARefusedEntry(string $key, string $name, ?string $image, ?string $slot, string $message): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LootEntry($key, $name, $image, $slot);
    }

    public function testANameIsCheckedAgainstTheLimitGiven(): void
    {
        LootEntry::checkName(str_repeat('a', 10), 'A name', 10);

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A name is 1 to 9 characters of UTF-8, not blank.');

        LootEntry::checkName(str_repeat('a', 10), 'A name', 9);
    }
}
