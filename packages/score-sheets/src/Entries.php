<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * The participants of a match, its teams, and the values entered so far, by participant and category. It is a value:
 * `with()` and `without()` return new entries. A value not entered yet counts as 0 and leaves the sheet incomplete.
 */
final readonly class Entries
{
    public const int MAX_PARTICIPANTS = 100;

    public const int MAX_TEAMS = 50;

    /** @var non-empty-list<ParticipantId> */
    public array $participants;

    /** @var list<Team> */
    public array $teams;

    /**
     * @param list<ParticipantId>               $participants in the order the sheet shows them
     * @param list<Team>                        $teams        each member a participant, in one team at most
     * @param array<string, array<string, int>> $values       by participant then category, checked by `with()`
     *
     * @throws InvalidEntry when the participants or the teams do not hold together
     */
    public function __construct(
        array $participants,
        array $teams = [],
        private array $values = [],
    ) {
        $known = [];

        foreach ($participants as $participant) {
            $known[$participant->value] = true;
        }

        if ([] === $participants || \count($participants) > self::MAX_PARTICIPANTS || \count($known) !== \count($participants)) {
            throw InvalidEntry::because(\sprintf('A match has 1 to %d participants, each once.', self::MAX_PARTICIPANTS));
        }

        if (\count($teams) > self::MAX_TEAMS) {
            throw InvalidEntry::because(\sprintf('A match has at most %d teams.', self::MAX_TEAMS));
        }

        $teamIds = [];
        $placed = [];

        foreach ($teams as $team) {
            if (isset($teamIds[$team->id->value])) {
                throw InvalidEntry::because(\sprintf('The team "%s" is declared twice.', $team->id->value));
            }

            $teamIds[$team->id->value] = true;

            foreach ($team->members as $member) {
                if (!isset($known[$member->value]) || isset($placed[$member->value])) {
                    throw InvalidEntry::because(\sprintf('The member "%s" of the team "%s" is a participant of no other team.', $member->value, $team->id->value));
                }

                $placed[$member->value] = true;
            }
        }

        $this->participants = $participants;
        $this->teams = $teams;
    }

    /**
     * The entries with one more value, or one value corrected.
     *
     * @throws InvalidEntry when the participant, the category or the value is refused
     */
    public function with(SheetDefinition $sheet, ParticipantId $participant, string $category, int $value): self
    {
        $declared = $this->entered($sheet, $participant, $category);

        if ($value < $declared->min || $value > $declared->max) {
            throw InvalidEntry::because(\sprintf('The category "%s" takes a value between %d and %d.', $category, $declared->min, $declared->max));
        }

        $values = $this->values;
        $values[$participant->value][$category] = $value;

        return new self($this->participants, $this->teams, $values);
    }

    /**
     * The entries with one value erased, as if never entered.
     *
     * @throws InvalidEntry when the participant or the category is refused
     */
    public function without(SheetDefinition $sheet, ParticipantId $participant, string $category): self
    {
        $this->entered($sheet, $participant, $category);
        $values = $this->values;
        unset($values[$participant->value][$category]);

        return new self($this->participants, $this->teams, $values);
    }

    public function value(ParticipantId $participant, string $category): ?int
    {
        return $this->values[$participant->value][$category] ?? null;
    }

    /**
     * Whether every participant has a value in every entered category.
     */
    public function isComplete(SheetDefinition $sheet): bool
    {
        foreach ($sheet->categories() as $category) {
            foreach ($this->participants as $participant) {
                if ($category->isEntered() && null === $this->value($participant, $category->key)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{participants: list<string>, teams: list<array{id: string, members: list<string>}>, values: array<string, array<string, int>>}
     */
    public function toArray(): array
    {
        return [
            'participants' => array_map(static fn (ParticipantId $participant): string => $participant->value, $this->participants),
            'teams' => array_map(static fn (Team $team): array => ['id' => $team->id->value, 'members' => array_map(static fn (ParticipantId $member): string => $member->value, $team->members)], $this->teams),
            'values' => array_filter($this->values),
        ];
    }

    /**
     * @throws InvalidEntry when the data is not entries `toArray()` could have written for this sheet
     */
    public static function fromArray(SheetDefinition $sheet, mixed $data): self
    {
        if (!\is_array($data) || !\is_array($data['participants'] ?? null) || !\is_array($data['teams'] ?? null) || !\is_array($data['values'] ?? null)) {
            throw InvalidEntry::because('Entries have participants, teams and values.');
        }

        $teams = array_map(static fn (mixed $team): Team => \is_array($team) && \is_string($team['id'] ?? null) && \is_array($team['members'] ?? null)
            ? new Team(new TeamId($team['id']), array_values(array_map(self::participant(...), $team['members'])))
            : throw InvalidEntry::because('A team has an identifier and members.'), array_values($data['teams']));
        $entries = new self(array_values(array_map(self::participant(...), $data['participants'])), $teams);

        foreach ($data['values'] as $participant => $values) {
            if (!\is_array($values)) {
                throw InvalidEntry::because('The values of a participant are a map of categories.');
            }

            foreach ($values as $category => $value) {
                if (!\is_int($value)) {
                    throw InvalidEntry::because('A value is an integer.');
                }

                $entries = $entries->with($sheet, new ParticipantId((string) $participant), (string) $category, $value);
            }
        }

        return $entries;
    }

    private static function participant(mixed $id): ParticipantId
    {
        return \is_string($id) ? new ParticipantId($id) : throw InvalidEntry::because('A participant is an identifier.');
    }

    private function entered(SheetDefinition $sheet, ParticipantId $participant, string $category): Category
    {
        if (!\in_array($participant->value, array_map(static fn (ParticipantId $known): string => $known->value, $this->participants), true)) {
            throw InvalidEntry::because(\sprintf('The participant "%s" is not on the sheet.', $participant->value));
        }

        $declared = $sheet->category($category);

        if (!$declared instanceof Category || !$declared->isEntered()) {
            throw InvalidEntry::because(\sprintf('The category "%s" is not one the scorer enters.', $category));
        }

        return $declared;
    }
}
