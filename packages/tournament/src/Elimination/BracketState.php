<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\Result;

/**
 * A bracket filled with what is known: the qualifiers, the results by match, the participants who withdrew. A match
 * with one participant against a bye, or against a participant who withdrew before playing, is a walkover; a match both
 * sides lost leaves the next place empty, and the participant there goes through.
 */
final readonly class BracketState
{
    /**
     * @param array<int, MatchState> $matches by number
     */
    private function __construct(
        public Bracket $bracket,
        public array $matches,
    ) {
    }

    /**
     * @param array<string, string> $qualifiers the participant of each qualifier known, by `Slot::label()`
     * @param array<int, Result>    $results    by match
     * @param array<string, true>   $withdrawn  by participant
     */
    public static function of(Bracket $bracket, array $qualifiers = [], array $results = [], array $withdrawn = []): self
    {
        $states = [];

        foreach ($bracket->matches as $match) {
            [$first, $firstEmpty] = self::fill($match->first, $qualifiers, $states);
            [$second, $secondEmpty] = self::fill($match->second, $qualifiers, $states);
            $states[$match->id] = self::decide($match, $first, $firstEmpty, $second, $secondEmpty, $results[$match->id] ?? null, $withdrawn);
        }

        return new self($bracket, $states);
    }

    public function state(int $match): ?MatchState
    {
        return $this->matches[$match] ?? null;
    }

    /**
     * The winner of the final, once it is decided.
     */
    public function champion(): ?string
    {
        return $this->matches[$this->bracket->final()->id]->winner ?? null;
    }

    /**
     * The podium once the final is decided: the champion, the finalist, then the two losers of the semi-finals, who
     * share the third place, in the order of their matches. Empty before.
     *
     * @return list<string>
     */
    public function podium(): array
    {
        $final = $this->matches[$this->bracket->final()->id];

        if (!$final->decided || null === $final->winner) {
            return [];
        }

        $podium = [$final->winner];
        $finalist = $final->loser();

        if (null !== $finalist) {
            $podium[] = $finalist;
        }

        foreach ($this->bracket->round($this->bracket->rounds - 1) as $semi) {
            $loser = $this->matches[$semi->id]->loser();

            if (null !== $loser) {
                $podium[] = $loser;
            }
        }

        return $podium;
    }

    /**
     * The tables of a round of the bracket, numbered by match: each match with two participants, and each participant
     * who goes through without an opponent, as a bye that scores nothing.
     *
     * @return list<Pairing>
     */
    public function pairings(int $round): array
    {
        $pairings = [];

        foreach ($this->bracket->round($round) as $match) {
            $state = $this->matches[$match->id];

            if (null !== $state->first && null !== $state->second) {
                $pairings[] = new Pairing($match->id, $state->first, $state->second, [
                    new PairingReason(ReasonKind::Seeded, $state->first, $state->second),
                    new PairingReason(ReasonKind::Seeded, $state->second, $state->first),
                ]);
            } elseif (null !== $state->winner) {
                $pairings[] = new Pairing($match->id, $state->winner, null, [new PairingReason(ReasonKind::Bye, $state->winner)], rest: true);
            }
        }

        return $pairings;
    }

    /**
     * The last round each participant reached, by participant: the champion one past the final.
     *
     * @return array<string, int>
     */
    public function reached(): array
    {
        $reached = [];

        foreach ($this->matches as $state) {
            foreach ([$state->first, $state->second] as $participant) {
                if (null !== $participant) {
                    $reached[$participant] = $state->match->round;
                }
            }
        }

        $champion = $this->champion();

        if (null !== $champion) {
            $reached[$champion] = $this->bracket->rounds + 1;
        }

        return $reached;
    }

    /**
     * @param array<string, string>  $qualifiers
     * @param array<int, MatchState> $states
     *
     * @return array{?string, bool} the participant, and whether the place is empty
     */
    private static function fill(Slot $slot, array $qualifiers, array $states): array
    {
        return match ($slot->kind) {
            SlotKind::Participant => [$slot->participant, false],
            SlotKind::Qualifier => [$qualifiers[$slot->label()] ?? null, false],
            SlotKind::Bye => [null, true],
            SlotKind::WinnerOf => [$states[$slot->match]->winner, $states[$slot->match]->decided && null === $states[$slot->match]->winner],
        };
    }

    /**
     * @param array<string, true> $withdrawn
     */
    private static function decide(BracketMatch $match, ?string $first, bool $firstEmpty, ?string $second, bool $secondEmpty, ?Result $result, array $withdrawn): MatchState
    {
        if (null !== $first && isset($withdrawn[$first]) && !$result instanceof Result) {
            [$first, $firstEmpty] = [null, true];
        }

        if (null !== $second && isset($withdrawn[$second]) && !$result instanceof Result) {
            [$second, $secondEmpty] = [null, true];
        }

        if ($firstEmpty || $secondEmpty) {
            $through = $firstEmpty ? $second : $first;
            $decided = ($firstEmpty && $secondEmpty) || null !== $through;

            return new MatchState($match, $first, $second, $firstEmpty, $secondEmpty, $through, $decided, null !== $through);
        }

        if (\in_array(null, [$first, $second, $result], true)) {
            return new MatchState($match, $first, $second);
        }

        $winner = match (true) {
            $result->outcome->firstWins() => $first,
            $result->outcome->secondWins() => $second,
            default => null,
        };

        return new MatchState($match, $first, $second, false, false, $winner, null !== $winner || Outcome::BothForfeit === $result->outcome);
    }
}
