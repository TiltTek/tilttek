<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Override;
use Tilttek\Encounter\Contract\DifficultyRules;
use Tilttek\Encounter\Encounter;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;
use Tilttek\Encounter\Kind;

/**
 * Rules of difficulty by level, as a game system declares them: each band has a budget for a party of a standard size,
 * raised or lowered by an amount for each character over or under it; each adversary costs an amount by how many levels
 * it stands above or below the party — the mean of the characters' levels, rounded down. An adversary further below the
 * party than the rules rate costs nothing; one further above is refused. Allies count neither way. The ratings are
 * whole levels.
 */
final readonly class LevelRules implements DifficultyRules
{
    /** The largest party of the standard size. */
    public const int MAX_PARTY_SIZE = 20;

    /** The furthest an adversary may stand from the party, either way, in the costs. */
    public const int MAX_DIFFERENCE = 50;

    /** The highest amount of a budget, an adjustment or a cost. */
    public const int MAX_AMOUNT = 1_000_000_000;

    private const string LEVEL = '/^-?[0-9]{1,3}$/D';

    /** @var list<array{string, int, int}> each band, its budget and its adjustment, from the easiest */
    private array $bands;

    /** @var array<int, int> */
    private array $costs;

    private int $lowest;

    private int $highest;

    /**
     * @param string                $system      the key of the game system
     * @param array<array-key, int> $budgets     by band, from the easiest, the budget for a party of the standard size
     * @param int                   $partySize   the standard size of a party, 1 to 20
     * @param array<array-key, int> $adjustments by band, what each character over the standard size adds, and each one under
     *                                           it takes away
     * @param array<int, int>       $costs       by how many levels an adversary stands above the party — negative below —
     *                                           what one copy costs, every difference from the lowest to the highest
     *
     * @throws InvalidEncounter when a value is out of its shape or its bounds
     */
    public function __construct(private string $system, array $budgets, private int $partySize, array $adjustments, array $costs)
    {
        Identifier::check($system, 'a game system');
        if ([] === $budgets || \count($budgets) > Assessment::MAX_BANDS) {
            throw InvalidEncounter::because(\sprintf('Rules of difficulty declare 1 to %d bands.', Assessment::MAX_BANDS));
        }

        $declared = [];
        foreach ($budgets as $band => $budget) {
            $adjustment = $adjustments[$band] ?? throw InvalidEncounter::because(\sprintf('The rules give no adjustment to the band %s.', $band));
            $this->amount(abs($adjustment), 'An adjustment, either way,');
            $declared[] = [Identifier::check((string) $band, 'a band of difficulty'), $this->amount($budget, 'A budget'), $adjustment];
        }

        $ordered = $budgets;
        sort($ordered);
        if ($ordered !== array_values($budgets)) {
            throw InvalidEncounter::because('The budgets grow with the bands, from the easiest.');
        }

        if (\count($adjustments) !== \count($budgets)) {
            throw InvalidEncounter::because('An adjustment names a band the rules do not declare.');
        }

        if ($partySize < 1 || $partySize > self::MAX_PARTY_SIZE) {
            throw InvalidEncounter::because(\sprintf('The standard size of a party is 1 to %d.', self::MAX_PARTY_SIZE));
        }

        if ([] === $costs) {
            throw InvalidEncounter::because('The rules give a cost to one level difference or more.');
        }

        $this->lowest = min(array_keys($costs));
        $this->highest = max(array_keys($costs));
        if ($this->lowest < -self::MAX_DIFFERENCE || $this->highest > self::MAX_DIFFERENCE) {
            throw InvalidEncounter::because(\sprintf('The costs are for differences of -%1$d to %1$d levels.', self::MAX_DIFFERENCE));
        }

        if (\count($costs) !== $this->highest - $this->lowest + 1) {
            throw InvalidEncounter::because('The costs give every level difference from the lowest to the highest.');
        }

        foreach ($costs as $cost) {
            $this->amount($cost, 'A cost');
        }

        $this->bands = $declared;
        $this->costs = $costs;
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

        $levels = [];
        foreach ($participants as $participant) {
            if (Kind::Character === $participant->kind) {
                $levels[] = $this->level($participant->rating);
            }
        }

        if ([] === $levels) {
            throw EncounterRefused::because('An encounter is assessed for one character or more.');
        }

        $party = (int) floor(array_sum($levels) / \count($levels));
        $cost = 0;
        $adversaries = 0;
        foreach ($participants as $participant) {
            if (Kind::Adversary !== $participant->kind) {
                continue;
            }

            $difference = $this->level($participant->rating) - $party;
            if ($difference > $this->highest) {
                throw EncounterRefused::because(\sprintf('The rules of %s rate no adversary %d levels above the party.', $this->system, $difference));
            }

            $cost += ($this->costs[$difference] ?? 0) * $participant->quantity;
            $adversaries += $participant->quantity;
        }

        $bands = [];
        foreach ($this->bands as [$band, $budget, $adjustment]) {
            $bands[] = new Band($band, max(0, $budget + (\count($levels) - $this->partySize) * $adjustment));
        }

        return new Assessment($this->system, $cost, $bands, \count($levels), $adversaries);
    }

    /**
     * @throws InvalidEncounter
     */
    private function level(string $rating): int
    {
        if (1 !== preg_match(self::LEVEL, $rating)) {
            throw InvalidEncounter::because(\sprintf('The rules of %s rate whole levels, not %s.', $this->system, $rating));
        }

        return (int) $rating;
    }

    /**
     * @throws InvalidEncounter
     */
    private function amount(int $amount, string $what): int
    {
        if ($amount < 0 || $amount > self::MAX_AMOUNT) {
            throw InvalidEncounter::because(\sprintf('%s is 0 to %d.', $what, self::MAX_AMOUNT));
        }

        return $amount;
    }
}
