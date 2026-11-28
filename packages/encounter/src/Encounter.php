<?php

declare(strict_types=1);

namespace Tilttek\Encounter;

use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;
use Tilttek\Encounter\Internal\Text;

/**
 * An encounter the game master prepares: its game system, an optional title, its participants in the order the game
 * master set, whether it was launched — handed to an initiative tracker, after which it no longer changes — and its
 * revision in the store.
 */
final readonly class Encounter
{
    /** The most lines of participants. */
    public const int MAX_PARTICIPANTS = 100;

    /** The most combatants, every copy counted. */
    public const int MAX_COMBATANTS = 100;

    /** The most characters of a title. */
    public const int MAX_TITLE = 200;

    /** @var list<Participant> */
    public array $participants;

    public ?string $title;

    /**
     * @param string            $id           the encounter's identifier
     * @param string            $gameMaster   the member who prepares it, and alone changes it
     * @param string            $system       the key of its game system, `srd-5.2`
     * @param list<Participant> $participants in the order the game master set, each identifier once
     * @param ?string           $title        one line up to 200 characters, or null
     * @param int               $revision     0 for an encounter never kept
     *
     * @throws InvalidEncounter when a value is out of its shape or its bounds, or a combatant identifier comes twice
     */
    public function __construct(
        public string $id,
        public string $gameMaster,
        public string $system,
        array $participants = [],
        ?string $title = null,
        public bool $launched = false,
        public int $revision = 0,
    ) {
        Identifier::check($id, 'an encounter');
        Identifier::check($gameMaster, 'a member');
        Identifier::check($system, 'a game system');
        $this->title = null === $title ? null : Text::line($title, self::MAX_TITLE, 'the title of an encounter');
        if (\count($participants) > self::MAX_PARTICIPANTS) {
            throw InvalidEncounter::because(\sprintf('An encounter holds at most %d participants.', self::MAX_PARTICIPANTS));
        }

        $seen = [];
        foreach ($participants as $participant) {
            if (isset($seen[$participant->id])) {
                throw InvalidEncounter::because(\sprintf('The participant %s comes twice in the encounter.', $participant->id));
            }

            $seen[$participant->id] = true;
        }

        $combatants = [];
        foreach ($participants as $participant) {
            foreach ($participant->combatants() as $combatant) {
                if (self::MAX_COMBATANTS === \count($combatants)) {
                    throw InvalidEncounter::because(\sprintf('An encounter counts at most %d combatants.', self::MAX_COMBATANTS));
                }

                if (isset($combatants[$combatant])) {
                    throw InvalidEncounter::because(\sprintf('The combatant %s comes twice in the encounter.', $combatant));
                }

                $combatants[$combatant] = true;
            }
        }

        if ($revision < 0) {
            throw InvalidEncounter::because('The revision of an encounter is 0 or more.');
        }

        $this->participants = $participants;
    }

    /**
     * The participant, or null when the encounter has none of the identifier.
     */
    public function participant(string $id): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->id === $id) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * The participants of one kind, in order.
     *
     * @return list<Participant>
     */
    public function of(Kind $kind): array
    {
        return array_values(array_filter($this->participants, static fn (Participant $participant): bool => $participant->kind === $kind));
    }

    /**
     * How many combatants the encounter counts, every copy counted.
     */
    public function combatants(): int
    {
        return array_sum(array_map(static fn (Participant $participant): int => $participant->quantity, $this->participants));
    }

    /**
     * @param list<Participant> $participants
     *
     * @throws InvalidEncounter
     */
    public function with(array $participants): self
    {
        return new self($this->id, $this->gameMaster, $this->system, $participants, $this->title, $this->launched, $this->revision);
    }

    public function withTitle(?string $title): self
    {
        return new self($this->id, $this->gameMaster, $this->system, $this->participants, $title, $this->launched, $this->revision);
    }

    public function launch(): self
    {
        return new self($this->id, $this->gameMaster, $this->system, $this->participants, $this->title, true, $this->revision);
    }

    public function withRevision(int $revision): self
    {
        return new self($this->id, $this->gameMaster, $this->system, $this->participants, $this->title, $this->launched, $revision);
    }
}
