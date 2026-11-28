<?php

declare(strict_types=1);

namespace Tilttek\Party;

use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Exception\PartyRefused;

/**
 * The coins a party holds in common, by currency: whole counts of the game's smallest coin under a key the application
 * chooses — `gp`, `sp`, `credits` —, never real money. A currency key starts with a letter, so that it stays a string
 * key of the amounts.
 *
 * A purse holds at most 20 currencies, each 0 to 1,000,000,000,000 coins; a currency at zero is dropped. Currencies are
 * read in the order they were first deposited.
 */
final readonly class Purse
{
    public const int MAX_CURRENCIES = 20;

    public const int MAX_AMOUNT = 1_000_000_000_000;

    /** @var array<string, int> */
    private array $amounts;

    /**
     * @param array<string, int> $amounts by currency key
     *
     * @throws InvalidParty
     */
    public function __construct(array $amounts = [])
    {
        $kept = [];

        foreach ($amounts as $currency => $amount) {
            Text::currency($currency);

            if ($amount < 0 || $amount > self::MAX_AMOUNT) {
                throw InvalidParty::because(\sprintf('A purse holds 0 to %d of a currency; %d of "%s" is out of bounds.', self::MAX_AMOUNT, $amount, $currency));
            }

            if (0 !== $amount) {
                $kept[$currency] = $amount;
            }
        }

        if (\count($kept) > self::MAX_CURRENCIES) {
            throw InvalidParty::because(\sprintf('A purse holds at most %d currencies.', self::MAX_CURRENCIES));
        }

        $this->amounts = $kept;
    }

    public function amount(string $currency): int
    {
        return $this->amounts[$currency] ?? 0;
    }

    /**
     * @return array<string, int> by currency key, the first deposited first
     */
    public function amounts(): array
    {
        return $this->amounts;
    }

    public function isEmpty(): bool
    {
        return [] === $this->amounts;
    }

    /**
     * The purse with the coins added.
     *
     * @param int $amount 1 to `MAX_AMOUNT`
     *
     * @throws InvalidParty when the amount, or the total it makes, is out of bounds
     */
    public function deposit(string $currency, int $amount): self
    {
        $this->checkAmount($amount);
        $total = $this->amount(Text::currency($currency)) + $amount;

        if ($total > self::MAX_AMOUNT) {
            throw InvalidParty::because(\sprintf('A purse holds at most %d of a currency; the deposit would make %d of "%s".', self::MAX_AMOUNT, $total, $currency));
        }

        return new self([...$this->amounts, $currency => $total]);
    }

    /**
     * The purse with the coins taken out.
     *
     * @param int $amount 1 to `MAX_AMOUNT`
     *
     * @throws InvalidParty when the amount is out of bounds
     * @throws PartyRefused when the purse holds less than the amount
     */
    public function withdraw(string $currency, int $amount): self
    {
        $this->checkAmount($amount);
        $held = $this->amount(Text::currency($currency));

        if ($amount > $held) {
            throw PartyRefused::because(Refusal::NotEnoughFunds);
        }

        return new self([...$this->amounts, $currency => $held - $amount]);
    }

    private function checkAmount(int $amount): void
    {
        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw InvalidParty::because(\sprintf('An amount is 1 to %d coins; %d is not.', self::MAX_AMOUNT, $amount));
        }
    }
}
