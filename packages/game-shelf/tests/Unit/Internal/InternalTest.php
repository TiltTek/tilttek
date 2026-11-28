<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Internal\Identifier;
use Tilttek\GameShelf\Internal\Text;

#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidShelf::class)]
final class InternalTest extends TestCase
{
    public function testAMemberIsAnOpaqueIdentifier(): void
    {
        self::assertSame('member-1', Identifier::member('member-1'));
        self::assertSame('a'.str_repeat('b', 127), Identifier::member('a'.str_repeat('b', 127)));
        self::assertSame('m.1:x_y', Identifier::member('m.1:x_y'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notMembers(): iterable
    {
        yield 'empty' => [''];
        yield 'digit first' => ['1member'];
        yield 'an address' => ['someone@example.org'];
        yield 'too long' => ['a'.str_repeat('b', 128)];
        yield 'a space' => ['member 1'];
        yield 'a line break after' => ["member\n"];
    }

    #[DataProvider('notMembers')]
    public function testAMemberIsRefusedOutOfItsShape(string $member): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        Identifier::member($member);
    }

    public function testAGameMayHoldAnAt(): void
    {
        self::assertSame('game@source', Identifier::game('game@source'));
        self::assertSame('a'.str_repeat('b', 127), Identifier::game('a'.str_repeat('b', 127)));
    }

    public function testAGameIsRefusedOutOfItsShape(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a copy is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');

        Identifier::game("copy\n", 'a copy');
    }

    public function testAGameIsRefusedWhenTooLong(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a game is');

        Identifier::game('a'.str_repeat('b', 128));
    }

    public function testALineIsTrimmedAndBoundedInCharacters(): void
    {
        self::assertSame('Édition', Text::line('  Édition ', 7, 'edition'));
        self::assertSame(str_repeat('é', 3), Text::line(str_repeat('é', 3), 3, 'edition'));
        self::assertSame('👨‍👩‍👧', Text::line('👨‍👩‍👧', 10, 'tag'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notLines(): iterable
    {
        yield 'empty' => ['  ', 'The edition is 1 to 3 characters.'];
        yield 'too long' => ['abcd', 'The edition is 1 to 3 characters.'];
        yield 'not UTF-8' => ["\xC3", 'The edition is not valid UTF-8.'];
        yield 'a line break' => ["a\nb", 'The edition holds a control character or a direction override.'];
        yield 'a tab' => ["a\tb", 'The edition holds a control character or a direction override.'];
        yield 'an override' => ["a\u{202E}b", 'The edition holds a control character or a direction override.'];
        yield 'an embedding' => ["a\u{202A}", 'The edition holds a control character or a direction override.'];
        yield 'an isolate' => ["a\u{2066}", 'The edition holds a control character or a direction override.'];
        yield 'the last isolate' => ["a\u{2069}", 'The edition holds a control character or a direction override.'];
    }

    #[DataProvider('notLines')]
    public function testALineIsRefusedOutOfItsShape(string $value, string $message): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains($message);

        Text::line($value, 3, 'edition');
    }

    public function testACommentKeepsItsLineBreaks(): void
    {
        self::assertSame("one\ntwo", Text::lines(" one\r\ntwo\n", 7, 'comment'));
    }

    public function testACommentIsRefusedWithAnotherControlCharacter(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The comment holds a control character or a direction override.');

        Text::lines("one\x07two", 20, 'comment');
    }

    public function testACommentIsRefusedWithAnOverride(): void
    {
        $this->expectException(InvalidShelf::class);

        Text::lines("one\u{202D}two", 20, 'comment');
    }

    public function testACommentIsBoundedAfterItsLineBreaksAreJoined(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The comment is 1 to 3 characters.');

        Text::lines("a\r\nbc", 3, 'comment');
    }
}
