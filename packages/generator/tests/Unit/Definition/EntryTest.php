<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Definition;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Exception\InvalidDefinition;

#[CoversClass(Entry::class)]
final class EntryTest extends TestCase
{
    public function testAnEntryIsItsTextWithItsWeightFieldsAndTags(): void
    {
        $entry = new Entry('épée', 3, ['gender' => 'f', 'price' => 12], ['kind' => ['blade', 'weapon']]);

        self::assertSame('épée', (string) $entry);
        self::assertSame(3, $entry->weight);
        self::assertSame(['gender' => 'f', 'price' => 12], $entry->fields());
        self::assertSame(['kind' => ['blade', 'weapon']], $entry->tags());
        self::assertSame(['text' => 'épée', 'gender' => 'f', 'price' => 12], $entry->toArray());
        self::assertSame(1, new Entry('arc')->weight);
    }

    public function testItIsReadLikeAnArray(): void
    {
        $entry = new Entry('épée', 1, ['price' => 12, 'empty' => ''], ['kind' => ['blade']]);

        self::assertTrue(isset($entry['text']));
        self::assertTrue(isset($entry['tags']));
        self::assertTrue(isset($entry['price']));
        self::assertTrue(isset($entry['empty']));
        self::assertFalse(isset($entry['weight']));
        self::assertFalse(isset($entry['kind']));
        self::assertSame('épée', $entry['text']);
        self::assertSame(['kind' => ['blade']], $entry['tags']);
        self::assertSame(12, $entry['price']);
        self::assertNull($entry['missing']);
    }

    public function testItCannotBeChanged(): void
    {
        $entry = new Entry('épée');

        try {
            $entry['text'] = 'arc';
            self::fail('An entry was changed.');
        } catch (LogicException $logicException) {
            self::assertSame('An entry cannot be changed.', $logicException->getMessage());
        }

        $this->expectExceptionObject(new LogicException('An entry cannot be changed.'));
        unset($entry['text']);
    }

    public function testItMatchesTheTagsAskedFor(): void
    {
        $entry = new Entry('épée', 1, [], ['kind' => ['blade', 'weapon'], 'era' => ['old']]);

        self::assertTrue($entry->matches([]));
        self::assertTrue($entry->matches(['kind' => ['weapon']]));
        self::assertTrue($entry->matches(['kind' => ['bow', 'blade'], 'era' => ['old']]));
        self::assertFalse($entry->matches(['kind' => ['blade'], 'era' => ['new']]));
        self::assertFalse($entry->matches(['colour' => ['red']]));
        self::assertFalse(new Entry('x')->matches(['kind' => ['blade']]));
    }

    public function testItGivesAGrammaticalValueFromAFieldOrATag(): void
    {
        $entry = new Entry('épée', 1, ['gender' => 'f', 'count' => 2, 'forms' => ['a', 'b']], ['gender' => ['m'], 'number' => ['p', 's']]);

        self::assertSame('f', $entry->grammar('gender'));
        self::assertSame('p', $entry->grammar('number'));
        self::assertSame('2', $entry->grammar('count'));
        self::assertNull($entry->grammar('forms'));
        self::assertNull($entry->grammar('plural'));
    }

    public function testTheWeightIsAtLeastOne(): void
    {
        new Entry('a', 1);
        $this->expectExceptionObject(InvalidDefinition::at('a', 'the weight of an entry is a whole number of at least 1'));

        new Entry('a', 0);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function reserved(): iterable
    {
        yield ['text'];
        yield ['weight'];
        yield ['tags'];
    }

    #[DataProvider('reserved')]
    public function testAFieldCannotTakeAReservedName(string $name): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('a', \sprintf('"%s" is not a field name', $name)));

        new Entry('a', 1, [$name => 'x']);
    }
}
