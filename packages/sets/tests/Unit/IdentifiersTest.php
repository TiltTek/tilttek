<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Identifiers;

#[CoversClass(Identifiers::class)]
#[CoversClass(InvalidSet::class)]
final class IdentifiersTest extends TestCase
{
    public function testCodesOfTheirShapeAreKept(): void
    {
        self::assertSame('a', Identifiers::code('a', 'set'));
        self::assertSame('0-dice_set.v2', Identifiers::code('0-dice_set.v2', 'set'));
        self::assertSame(str_repeat('a', 64), Identifiers::code(str_repeat('a', 64), 'set'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Dice'];
        yield 'leading dash' => ['-dice'];
        yield 'leading underscore' => ['_dice'];
        yield 'space' => ['dice set'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["dice\n"];
        yield 'colon' => ['a:b'];
    }

    #[DataProvider('refusedCodes')]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The code of a tier is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "'.substr($code, 0, 64).'" is not.');

        Identifiers::code($code, 'tier');
    }

    public function testMembersOfTheirShapeAreKept(): void
    {
        self::assertSame('Card:42/b-1_x.y', Identifiers::member('Card:42/b-1_x.y'));
        self::assertSame('7', Identifiers::member('7'));
        self::assertSame(str_repeat('Z', 255), Identifiers::member(str_repeat('Z', 255)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedMembers(): iterable
    {
        yield 'empty' => [''];
        yield 'at sign' => ['someone@example.org'];
        yield 'leading colon' => [':a'];
        yield 'space' => ['a b'];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'trailing newline' => ["a\n"];
    }

    #[DataProvider('refusedMembers')]
    public function testOtherMembersAreRefused(string $member): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('A member of a set is 1 to 255 letters, digits, "_", ".", ":", "/" or "-", starting with a letter or a digit; "'.substr($member, 0, 64).'" is not.');

        Identifiers::member($member);
    }

    public function testAHolderOfAStableIdentifierIsKept(): void
    {
        $holder = ResourceReference::of('member', '0192f3c4-1');

        self::assertSame($holder, Identifiers::holder($holder));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emailIdentifiers(): iterable
    {
        yield 'plain' => ['someone@example.org'];
        yield 'encoded' => ['someone%40example.org'];
        yield 'encoded lower case' => ['someone%40'];
        yield 'encoded upper case' => ['SOMEONE%40EXAMPLE'];
    }

    #[DataProvider('emailIdentifiers')]
    public function testAHolderReferencedByAnEmailAddressIsRefused(string $id): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The holder "member:…" has an identifier with "@": a holder is referenced by a stable identifier, never by an e-mail address.');

        Identifiers::holder(ResourceReference::of('member', $id));
    }

    public function testAnIdentifierThatStartsWithTheEncodedSignIsRefused(): void
    {
        $this->expectException(InvalidSet::class);

        Identifiers::holder(ResourceReference::of('member', '%40x'));
    }
}
