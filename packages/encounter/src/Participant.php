<?php

declare(strict_types=1);

namespace Tilttek\Encounter;

use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;
use Tilttek\Encounter\Internal\Rating;

/**
 * One line of an encounter prepared by the game master: a character, or one or more copies of an adversary or an ally
 * of one model, with the rating the game system gives it — a level, a challenge — the member who controls it, whether
 * the players see it, and its initiative bonus. The application keeps names and statistics in its own records, which
 * `$ref` points to.
 */
final readonly class Participant
{
    /** The most copies of one model on one line. */
    public const int MAX_QUANTITY = 50;

    /** The most characters of a participant's identifier, so that the identifiers of its copies stay identifiers. */
    public const int MAX_ID_LENGTH = 124;

    /** The bound, either way, of an initiative bonus. */
    public const int MAX_INITIATIVE_BONUS = 99;

    /**
     * @param string  $id              the participant's identifier, unique in the encounter, up to 124 characters
     * @param string  $rating          its level or challenge as the game system writes it, up to 16 letters, digits, "/",
     *                                 ".", "+" or "-"
     * @param string  $controller      the member who plays it: a player for their character, the game master for the others
     * @param int     $quantity        1 to 50 copies of the model; a character is always one
     * @param bool    $hidden          whether the players do not see it: only the game master does
     * @param ?int    $initiativeBonus what it adds to its initiative, -99 to 99, or null when the system adds nothing
     * @param ?string $ref             the identifier of the application's record — a character sheet, a stat block — or null
     *
     * @throws InvalidEncounter when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $id,
        public Kind $kind,
        public string $rating,
        public string $controller,
        public int $quantity = 1,
        public bool $hidden = false,
        public ?int $initiativeBonus = null,
        public ?string $ref = null,
    ) {
        Identifier::check($id, 'a participant', self::MAX_ID_LENGTH);
        Identifier::check($controller, 'a member');
        if (null !== $ref) {
            Identifier::check($ref, 'a record');
        }

        Rating::check($rating, \sprintf('the rating of the participant %s', $id));

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidEncounter::because(\sprintf('A participant counts 1 to %d copies.', self::MAX_QUANTITY));
        }

        if (Kind::Character === $kind && 1 !== $quantity) {
            throw InvalidEncounter::because(\sprintf('The character %s is one participant.', $id));
        }

        if (null !== $initiativeBonus && abs($initiativeBonus) > self::MAX_INITIATIVE_BONUS) {
            throw InvalidEncounter::because(\sprintf('An initiative bonus is -%1$d to %1$d.', self::MAX_INITIATIVE_BONUS));
        }
    }

    /**
     * Whether the other participant is this one in every value.
     */
    public function sameAs(self $other): bool
    {
        return [$this->id, $this->kind, $this->rating, $this->controller, $this->quantity, $this->hidden, $this->initiativeBonus, $this->ref]
            === [$other->id, $other->kind, $other->rating, $other->controller, $other->quantity, $other->hidden, $other->initiativeBonus, $other->ref];
    }

    /**
     * The identifiers of its copies, as a tracker knows them: its own identifier when it is one, `goblins.1` to
     * `goblins.n` otherwise.
     *
     * @return non-empty-list<string>
     */
    public function combatants(): array
    {
        if (1 === $this->quantity) {
            return [$this->id];
        }

        return array_map(fn (int $copy): string => $this->id.'.'.$copy, range(1, $this->quantity));
    }
}
