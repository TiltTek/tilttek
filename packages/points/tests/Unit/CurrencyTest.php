<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\UnknownCurrency;

#[CoversClass(Currency::class)]
#[CoversClass(Currencies::class)]
#[CoversClass(UnknownCurrency::class)]
final class CurrencyTest extends TestCase
{
    public function testACurrency(): void
    {
        $crowns = new Currency('crowns');
        self::assertSame('crowns', $crowns->code);
        self::assertNull($crowns->lifetime);
        self::assertNull($crowns->lifetimeSpec);
        self::assertNull($crowns->expiresAt(new DateTimeImmutable('2026-01-31')));

        $stars = new Currency('stars', 'P1M');
        self::assertSame('P1M', $stars->lifetimeSpec);
        self::assertSame('2026-03-03', $stars->expiresAt(new DateTimeImmutable('2026-01-31'))?->format('Y-m-d'));
        self::assertSame('2026-11-07 12:00:01', new Currency('blink', 'PT1S')->expiresAt(new DateTimeImmutable('2026-11-07 12:00:00'))?->format('Y-m-d H:i:s'));
        self::assertSame('a', new Currency('a')->code);
        self::assertSame(32, \strlen(new Currency('a'.str_repeat('_9', 15).'z')->code));
    }

    public function testCodesAreChecked(): void
    {
        foreach (['', 'Crowns', '9crowns', '_x', 'cr-owns', 'a'.str_repeat('b', 32), "crowns\n"] as $code) {
            try {
                new Currency($code);
                self::fail(\sprintf('"%s" was expected to be refused.', $code));
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('"%s" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.', $code), $invalidPoints->getMessage());
            }
        }

        self::assertSame('points', Currency::checkCode('points'));
    }

    public function testLifetimesAreChecked(): void
    {
        try {
            new Currency('stars', 'one year');
            self::fail('A lifetime that is no duration was expected to be refused.');
        } catch (InvalidPoints $invalidPoints) {
            self::assertSame('"one year" is not a lifetime: an ISO 8601 duration such as "P1Y" or "P6M".', $invalidPoints->getMessage());
        }

        $this->expectExceptionObject(InvalidPoints::because('A lifetime is longer than nothing; "PT0S" is not.'));
        new Currency('stars', 'PT0S');
    }

    public function testTheCurrencies(): void
    {
        $currencies = new Currencies(new Currency('points'), new Currency('stars', 'P1Y'));

        self::assertSame('points', $currencies->default()->code);
        self::assertSame('points', $currencies->get()->code);
        self::assertSame('stars', $currencies->get('stars')->code);
        self::assertSame(['points', 'stars'], array_map(static fn (Currency $currency): string => $currency->code, $currencies->all()));

        $this->expectExceptionObject(UnknownCurrency::named('crowns'));
        $currencies->get('crowns');
    }

    public function testTwoCurrenciesCannotShareACode(): void
    {
        $this->expectExceptionObject(InvalidPoints::because('Two currencies are named "points".'));
        new Currencies(new Currency('points'), new Currency('stars'), new Currency('points'));
    }

    public function testTheCurrenciesFromPlainValues(): void
    {
        $currencies = Currencies::fromArray(['points' => null, 'stars' => 'P1Y', 'crowns' => null]);
        self::assertSame(['points', 'stars', 'crowns'], array_map(static fn (Currency $currency): string => $currency->code, $currencies->all()));
        self::assertSame('P1Y', $currencies->get('stars')->lifetimeSpec);

        $named = Currencies::fromArray(['points' => null, 'stars' => 'P1Y', 'crowns' => null], 'stars');
        self::assertSame(['stars', 'points', 'crowns'], array_map(static fn (Currency $currency): string => $currency->code, $named->all()));
        self::assertSame('P1Y', $named->default()->lifetimeSpec);

        try {
            Currencies::fromArray(['points' => null], 'stars');
            self::fail('An unknown default was expected to be refused.');
        } catch (UnknownCurrency $unknownCurrency) {
            self::assertSame('No currency of points is named "stars".', $unknownCurrency->getMessage());
        }

        $this->expectExceptionObject(InvalidPoints::because('An application has at least one currency of points.'));
        Currencies::fromArray([]);
    }
}
