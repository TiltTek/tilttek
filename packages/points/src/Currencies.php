<?php

declare(strict_types=1);

namespace Tilttek\Points;

use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\UnknownCurrency;

/**
 * The currencies of points of an application, at least one; the first is the default, used wherever no currency is
 * named.
 */
final readonly class Currencies
{
    /**
     * @var non-empty-array<string, Currency>
     */
    private array $currencies;

    public function __construct(Currency $default, Currency ...$others)
    {
        $byCode = [$default->code => $default];

        foreach ($others as $currency) {
            if (isset($byCode[$currency->code])) {
                throw InvalidPoints::because(\sprintf('Two currencies are named "%s".', $currency->code));
            }

            $byCode[$currency->code] = $currency;
        }

        $this->currencies = $byCode;
    }

    /**
     * The currencies from plain values, as a configuration gives them: each code with its lifetime or null, the
     * default named, or the first when it is not.
     *
     * @param array<string, ?string> $lifetimes
     */
    public static function fromArray(array $lifetimes, ?string $default = null): self
    {
        if ([] === $lifetimes) {
            throw InvalidPoints::because('An application has at least one currency of points.');
        }

        $code = $default ?? array_key_first($lifetimes);

        if (!\array_key_exists($code, $lifetimes)) {
            throw UnknownCurrency::named($code);
        }

        $others = [];

        foreach ($lifetimes as $other => $lifetime) {
            if ($other !== $code) {
                $others[] = new Currency($other, $lifetime);
            }
        }

        return new self(new Currency($code, $lifetimes[$code]), ...$others);
    }

    /**
     * The currency of this code, or the default one when no code is given.
     *
     * @throws UnknownCurrency
     */
    public function get(?string $code = null): Currency
    {
        return null === $code ? $this->default() : $this->currencies[$code] ?? throw UnknownCurrency::named($code);
    }

    public function default(): Currency
    {
        return array_first($this->currencies);
    }

    /**
     * @return non-empty-list<Currency>
     */
    public function all(): array
    {
        return array_values($this->currencies);
    }
}
