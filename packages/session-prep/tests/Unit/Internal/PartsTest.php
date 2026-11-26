<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Parts;
use Tilttek\SessionPrep\Note;
use Tilttek\SessionPrep\Part;

#[CoversClass(Parts::class)]
final class PartsTest extends TestCase
{
    public function testDistinctPartsAreKeptInTheirOrderUpToTheMost(): void
    {
        $parts = $this->notes('a', 'b');

        self::assertSame($parts, Parts::distinct($parts, 2, 'notes'));
    }

    public function testTooManyPartsAreRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('A preparation holds at most 1 notes.'));

        Parts::distinct($this->notes('a', 'b'), 1, 'notes');
    }

    public function testAKeyTwiceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The key b comes twice among the notes.'));

        Parts::distinct($this->notes('a', 'b', 'c', 'b'), 10, 'notes');
    }

    public function testAPartIsFoundByItsKey(): void
    {
        $parts = $this->notes('a', 'b');

        self::assertSame($parts[1], Parts::find($parts, 'b'));
        self::assertSame($parts[0], Parts::get($parts, 'a', 'note'));
        self::assertNull(Parts::find($parts, 'c'));
    }

    public function testGettingAMissingPartIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('No note has the key c.'));

        Parts::get($this->notes('a'), 'c', 'note');
    }

    public function testAPartIsPutInPlaceOrAfterTheOthers(): void
    {
        $replaced = new Note('a', 'new');

        self::assertSame(['a', 'b'], $this->keys(Parts::put($this->notes('a', 'b'), $replaced)));
        self::assertSame('new', Parts::put($this->notes('a', 'b'), $replaced)[0]->text);
        self::assertSame(['b', 'c', 'a'], $this->keys(Parts::put($this->notes('b', 'c'), $replaced)));
    }

    public function testAPartIsRemovedAndTheOthersKeepTheirOrder(): void
    {
        self::assertSame(['a', 'c'], $this->keys(Parts::remove($this->notes('a', 'b', 'c'), 'b', 'note')));
        self::assertSame([0, 1], array_keys(Parts::remove($this->notes('a', 'b', 'c'), 'a', 'note')));
    }

    public function testRemovingAMissingPartIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('No note has the key d.'));

        Parts::remove($this->notes('a'), 'd', 'note');
    }

    public function testAPartMovesBeforeAnotherOrLast(): void
    {
        $parts = $this->notes('a', 'b', 'c', 'd');

        self::assertSame(['a', 'd', 'b', 'c'], $this->keys(Parts::move($parts, 'd', 'b', 'note')));
        self::assertSame(['b', 'c', 'a', 'd'], $this->keys(Parts::move($parts, 'a', 'd', 'note')));
        self::assertSame(['b', 'c', 'd', 'a'], $this->keys(Parts::move($parts, 'a', null, 'note')));
        self::assertSame(['c', 'a', 'b', 'd'], $this->keys(Parts::move($parts, 'c', 'a', 'note')));
    }

    public function testMovingAMissingPartIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('No note has the key z.'));

        Parts::move($this->notes('a', 'b'), 'z', 'a', 'note');
    }

    public function testMovingBeforeAMissingPartIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('No note has the key z.'));

        Parts::move($this->notes('a', 'b'), 'a', 'z', 'note');
    }

    public function testMovingBeforeItselfIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The note a cannot move before itself.'));

        Parts::move($this->notes('a', 'b'), 'a', 'a', 'note');
    }

    /**
     * @return list<Note>
     */
    private function notes(string ...$keys): array
    {
        return array_values(array_map(static fn (string $key): Note => new Note($key, 'Note '.$key), $keys));
    }

    /**
     * @param list<Part> $parts
     *
     * @return list<string>
     */
    private function keys(array $parts): array
    {
        return array_map(static fn (Part $part): string => $part->key, $parts);
    }
}
