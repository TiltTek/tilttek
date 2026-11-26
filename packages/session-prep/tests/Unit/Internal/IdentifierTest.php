<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;

#[CoversClass(Identifier::class)]
final class IdentifierTest extends TestCase
{
    public function testAnIdentifierIsUpTo128Characters(): void
    {
        $id = 'a'.str_repeat('B9_.:-', 21).'c';

        self::assertSame(128, \strlen($id));
        self::assertSame($id, Identifier::check($id, 'a preparation'));
        self::assertSame('x', Identifier::check('x', 'a preparation'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'a digit first' => ['1a'];
        yield 'an address' => ['gm@example.org'];
        yield 'a space' => ['a b'];
        yield 'a line end after' => ["ab\n"];
    }

    #[DataProvider('notIdentifiers')]
    public function testAnythingElseIsRefused(string $value): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The identifier of a campaign is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        Identifier::check($value, 'a campaign');
    }

    public function testAKeyIsUpTo64Characters(): void
    {
        $key = str_repeat('k', 64);

        self::assertSame($key, Identifier::key($key, 'a scene'));
    }

    public function testALongerKeyIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The key of a scene is 1 to 64 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        Identifier::key(str_repeat('k', 65), 'a scene');
    }

    public function testAKeyStartsWithALetter(): void
    {
        $this->expectException(InvalidPreparation::class);

        Identifier::key("k\n", 'a scene');
    }

    public function testAKeyHoldsOnlyItsCharactersFromItsFirst(): void
    {
        foreach (['9k', 'k k', 'k@k', ''] as $value) {
            try {
                Identifier::key($value, 'a clue');
                self::fail('Not refused: '.$value);
            } catch (InvalidPreparation $exception) {
                self::assertStringStartsWith('The key of a clue is 1 to 64', $exception->getMessage());
            }
        }
    }

    public function testKeysAreDistinctAndAtMostSoMany(): void
    {
        self::assertSame(['a', 'b'], Identifier::keys(['a', 'b'], 2, 'A list'));
        self::assertSame([], Identifier::keys([], 0, 'A list'));
    }

    public function testTooManyKeysAreRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('A list names at most 1.'));

        Identifier::keys(['a', 'b'], 1, 'A list');
    }

    public function testAKeyTwiceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('A list names a key twice.'));

        Identifier::keys(['a', 'b', 'a'], 3, 'A list');
    }

    public function testEachKeyIsChecked(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The key of a part is 1 to 64 letters, digits, "_", ".", ":" or "-", starting with a letter.'));

        Identifier::keys(['a', '9'], 3, 'A list');
    }
}
