<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

use DateTimeImmutable;
use Exception;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Refusal;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Movement;

/**
 * A reward of the catalogue: what a member gets for points — a discount, a digital item, an access, a seat at an
 * event —, its price in a currency of points, its stock, its period of availability, how many units one member may
 * hold, and the conditions a member meets to get it, like `badge:helper` or `level:5`, which the application's
 * `Requirements` checks.
 *
 * The stock counts every unit the reward ever has: the units reserved and those delivered use it up, the units of a
 * reservation cancelled or expired come back. Without a stock, the units never run out; without a limit, a member may
 * hold as many as they pay for.
 */
final readonly class Reward
{
    public const int MAX_REQUIREMENT_LENGTH = 255;

    /**
     * @param ?string      $currency the currency of the price, the default one when null
     * @param list<string> $requires the conditions a member meets, every one of them, to get the reward
     */
    public function __construct(
        public string $code,
        public int $price,
        public ?string $currency = null,
        public ?int $stock = null,
        public ?DateTimeImmutable $availableFrom = null,
        public ?DateTimeImmutable $availableUntil = null,
        public ?int $perMember = null,
        public array $requires = [],
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,99}$/D', $code)) {
            throw InvalidExchange::because(\sprintf('"%s" is not a reward code: 1 to 100 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.', $code));
        }

        if ($price < 1 || $price > Movement::MAX_AMOUNT) {
            throw InvalidExchange::because(\sprintf('A reward costs 1 to %d points; "%s" costs %d.', Movement::MAX_AMOUNT, $code, $price));
        }

        if (null !== $currency) {
            try {
                Currency::checkCode($currency);
            } catch (InvalidPoints $invalid) {
                throw InvalidExchange::because($invalid->getMessage());
            }
        }

        if (null !== $stock && $stock < 0) {
            throw InvalidExchange::because(\sprintf('A stock holds 0 units or more; "%s" holds %d.', $code, $stock));
        }

        if (null !== $perMember && $perMember < 1) {
            throw InvalidExchange::because(\sprintf('A member may hold at least one unit of a reward; %d is not a limit.', $perMember));
        }

        if ($availableFrom instanceof DateTimeImmutable && $availableUntil instanceof DateTimeImmutable && $availableUntil <= $availableFrom) {
            throw InvalidExchange::because(\sprintf('The availability of "%s" ends before it starts.', $code));
        }

        foreach ($requires as $requirement) {
            if ('' === $requirement || \strlen($requirement) > self::MAX_REQUIREMENT_LENGTH) {
                throw InvalidExchange::because(\sprintf('A requirement is 1 to %d bytes.', self::MAX_REQUIREMENT_LENGTH));
            }
        }
    }

    /**
     * The reward from a configuration: `price`, and optionally `currency`, `stock`, `available_from` and
     * `available_until` as dates PHP reads, `per_member` and `requires`.
     *
     * @param array{price: int, currency?: ?string, stock?: ?int, available_from?: ?string, available_until?: ?string, per_member?: ?int, requires?: list<string>} $spec
     */
    public static function fromArray(string $code, array $spec): self
    {
        return new self(
            $code,
            $spec['price'],
            $spec['currency'] ?? null,
            $spec['stock'] ?? null,
            self::date($spec['available_from'] ?? null),
            self::date($spec['available_until'] ?? null),
            $spec['per_member'] ?? null,
            $spec['requires'] ?? [],
        );
    }

    /**
     * Why the reward cannot be had at the moment — its period has not started or is over —; null when it can.
     */
    public function availability(DateTimeImmutable $at): ?Refusal
    {
        return match (true) {
            $this->availableFrom instanceof DateTimeImmutable && $at < $this->availableFrom => Refusal::NotYetAvailable,
            $this->availableUntil instanceof DateTimeImmutable && $at >= $this->availableUntil => Refusal::NoLongerAvailable,
            default => null,
        };
    }

    /**
     * What a number of units costs.
     *
     * @throws InvalidExchange when the quantity is below 1 or the cost goes past what a movement of points moves
     */
    public function cost(int $quantity): int
    {
        if ($quantity < 1) {
            throw InvalidExchange::because(\sprintf('A reservation holds at least one unit; %d is not a quantity.', $quantity));
        }

        if ($quantity > intdiv(Movement::MAX_AMOUNT, $this->price)) {
            throw InvalidExchange::because(\sprintf('%d units of "%s" cost more points than one movement moves.', $quantity, $this->code));
        }

        return $this->price * $quantity;
    }

    private static function date(?string $date): ?DateTimeImmutable
    {
        try {
            return null === $date ? null : new DateTimeImmutable($date);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not a date.', $date));
        }
    }
}
