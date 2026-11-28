<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Override;
use Tilttek\Encounter\Contract\DifficultyRules;
use Tilttek\Encounter\Encounter;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;
use Tilttek\Encounter\Internal\Rating;
use Tilttek\Encounter\Kind;

/**
 * Rules of difficulty by budget, as a game system declares them: each character brings an amount to each band by its
 * level, each adversary costs an amount by its challenge, and the cost of the adversaries may be multiplied by their
 * number. An encounter reaches a band once the cost of its adversaries meets the sum of its characters' amounts.
 * Allies count neither way.
 */
final readonly class BudgetRules implements DifficultyRules
{
    /** The most ratings of each table. */
    public const int MAX_RATINGS = 1000;

    /** The highest amount of a budget or a cost. */
    public const int MAX_AMOUNT = 1_000_000_000;

    /** The most multipliers. */
    public const int MAX_MULTIPLIERS = 20;

    /** @var list<string> */
    private array $bands;

    /** @var array<array-key, array<array-key, int>> by rating, then by band */
    private array $budgets;

    /** @var array<array-key, int> by rating */
    private array $costs;

    /** @var list<Multiplier> */
    private array $multipliers;

    /**
     * @param string                                  $system      the key of the game system
     * @param list<string>                            $bands       1 to 12 keys of bands, from the easiest
     * @param array<array-key, array<array-key, int>> $budgets     by a character's rating, its amount for each band, in order
     * @param array<array-key, int>                   $costs       by an adversary's rating, what one copy costs
     * @param list<Multiplier>                        $multipliers by the number of adversaries they hold from, in order
     *
     * @throws InvalidEncounter when a value is out of its shape or its bounds
     */
    public function __construct(private string $system, array $bands, array $budgets, array $costs, array $multipliers = [])
    {
        Identifier::check($system, 'a game system');
        if ([] === $bands || \count($bands) > Assessment::MAX_BANDS) {
            throw InvalidEncounter::because(\sprintf('Rules of difficulty declare 1 to %d bands.', Assessment::MAX_BANDS));
        }

        foreach ($bands as $band) {
            Identifier::check($band, 'a band of difficulty');
        }

        if (\count(array_unique($bands)) !== \count($bands)) {
            throw InvalidEncounter::because('A band of difficulty comes twice.');
        }

        $this->bounded($budgets, 'budgets');
        foreach ($budgets as $rating => $row) {
            $rating = Rating::check($rating, 'a rating of the budgets');
            $amounts = [];
            foreach ($bands as $band) {
                $amounts[] = $this->amount($row[$band] ?? throw InvalidEncounter::because(\sprintf('The budget of the rating %s has no amount for the band %s.', $rating, $band)), $rating);
            }

            $ordered = $amounts;
            sort($ordered);
            if ($ordered !== $amounts) {
                throw InvalidEncounter::because(\sprintf('The amounts of the rating %s grow with the bands, from the easiest.', $rating));
            }

            if (\count($row) !== \count($bands)) {
                throw InvalidEncounter::because(\sprintf('The budget of the rating %s names a band the rules do not declare.', $rating));
            }
        }

        $this->bounded($costs, 'costs');
        foreach ($costs as $rating => $cost) {
            $this->amount($cost, Rating::check($rating, 'a rating of the costs'));
        }

        if (\count($multipliers) > self::MAX_MULTIPLIERS) {
            throw InvalidEncounter::because(\sprintf('Rules of difficulty declare at most %d multipliers.', self::MAX_MULTIPLIERS));
        }

        $from = array_map(static fn (Multiplier $multiplier): int => $multiplier->from, $multipliers);
        $ordered = array_unique($from);
        sort($ordered);
        if ($ordered !== $from) {
            throw InvalidEncounter::because('The multipliers are in the order of the adversaries they hold from, each number once.');
        }

        $this->bands = $bands;
        $this->budgets = $budgets;
        $this->costs = $costs;
        $this->multipliers = $multipliers;
    }

    #[Override]
    public function system(): string
    {
        return $this->system;
    }

    #[Override]
    public function assess(array $participants): Assessment
    {
        if (\count($participants) > Encounter::MAX_PARTICIPANTS) {
            throw InvalidEncounter::because(\sprintf('An encounter holds at most %d participants.', Encounter::MAX_PARTICIPANTS));
        }

        $thresholds = array_fill_keys($this->bands, 0);
        $characters = 0;
        $adversaries = 0;
        $sum = 0;
        foreach ($participants as $participant) {
            if (Kind::Character === $participant->kind) {
                $row = $this->budgets[$participant->rating] ?? throw InvalidEncounter::because(\sprintf('The rules of %s give no budget to the rating %s.', $this->system, $participant->rating));
                foreach ($this->bands as $band) {
                    $thresholds[$band] += $row[$band];
                }

                ++$characters;
            } elseif (Kind::Adversary === $participant->kind) {
                $cost = $this->costs[$participant->rating] ?? throw InvalidEncounter::because(\sprintf('The rules of %s give no cost to the rating %s.', $this->system, $participant->rating));
                $sum += $cost * $participant->quantity;
                $adversaries += $participant->quantity;
            }
        }

        if (0 === $characters) {
            throw EncounterRefused::because('An encounter is assessed for one character or more.');
        }

        $percent = 100;
        foreach ($this->multipliers as $multiplier) {
            if ($multiplier->from <= $adversaries) {
                $percent = $multiplier->percent;
            }
        }

        $bands = [];
        foreach ($thresholds as $band => $threshold) {
            $bands[] = new Band($band, $threshold);
        }

        return new Assessment($this->system, intdiv($sum * $percent, 100), $bands, $characters, $adversaries);
    }

    /**
     * @param array<array-key, mixed> $table
     *
     * @throws InvalidEncounter
     */
    private function bounded(array $table, string $what): void
    {
        if ([] === $table || \count($table) > self::MAX_RATINGS) {
            throw InvalidEncounter::because(\sprintf('The %s hold 1 to %d ratings.', $what, self::MAX_RATINGS));
        }
    }

    /**
     * @throws InvalidEncounter
     */
    private function amount(int $amount, string $rating): int
    {
        if ($amount < 0 || $amount > self::MAX_AMOUNT) {
            throw InvalidEncounter::because(\sprintf('An amount of the rating %s is 0 to %d.', $rating, self::MAX_AMOUNT));
        }

        return $amount;
    }
}
