<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;

#[CoversClass(ExchangeKey::class)]
#[CoversClass(InvalidExchange::class)]
final class ExchangeKeyTest extends TestCase
{
    public function testAKeyKeepsItsValue(): void
    {
        self::assertSame('order:2026-0193:reward', ExchangeKey::fromString('order:2026-0193:reward')->value);
        self::assertSame(str_repeat('k', 200), ExchangeKey::fromString(str_repeat('k', 200))->value);
        self::assertSame('a#b', ExchangeKey::fromString('a#b')->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => ['', 'An exchange key is 1 to 200 bytes; this one is 0.'];
        yield 'too long' => [str_repeat('k', 201), 'An exchange key is 1 to 200 bytes; this one is 201.'];
        yield 'space' => ['a b', 'An exchange key cannot contain whitespace or control characters.'];
        yield 'tab' => ["a\tb", 'An exchange key cannot contain whitespace or control characters.'];
        yield 'control' => ["a\x01b", 'An exchange key cannot contain whitespace or control characters.'];
        yield 'delete' => ["a\x7Fb", 'An exchange key cannot contain whitespace or control characters.'];
        yield 'derived' => ['#refund:1', '"#refund:1" starts with "#", which only the keys the ledger derives start with.'];
    }

    #[DataProvider('refused')]
    public function testAKeyIsRefused(string $key, string $message): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains($message);

        ExchangeKey::fromString($key);
    }
}
