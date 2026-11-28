<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RandomCharacter\Exception\InvalidDocument;
use Tilttek\RandomCharacter\Exception\RollFailed;
use Tilttek\RandomCharacter\Internal\Fields;

#[CoversClass(Fields::class)]
#[CoversClass(InvalidDocument::class)]
#[CoversClass(RollFailed::class)]
final class FieldsTest extends TestCase
{
    public function testAListMayHoldAsManyItemsAsItsMaximum(): void
    {
        $fields = Fields::of(['items' => ['a', 'b', 'c']], '', ['items']);

        self::assertSame(['a', 'b', 'c'], $fields->list('items', 3));
    }

    public function testAListHoldsNoMoreThanItsMaximum(): void
    {
        $fields = Fields::of(['items' => ['a', 'b', 'c', 'd']], '', ['items']);

        $this->expectExceptionObject(InvalidDocument::at('items', 'a list of 1 to 3 items is expected.'));
        $fields->list('items', 3);
    }

    public function testTheExceptionsHaveNoCode(): void
    {
        self::assertSame(0, InvalidDocument::at('', 'bad')->getCode());
        self::assertSame(0, InvalidDocument::at('a', 'bad')->getCode());
        self::assertSame(0, RollFailed::because('bad')->getCode());
    }
}
