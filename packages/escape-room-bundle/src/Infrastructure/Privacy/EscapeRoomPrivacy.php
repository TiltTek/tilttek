<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;

/**
 * The escape rooms' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default): the games and results they played as the team, the screens they showed
 * last as a host, the bookings they made as the party.
 *
 * The erasure decides store by store, and `doc/privacy.md` gives the reasons:
 *
 * - the games over, their screens and every result of the team are deleted;
 * - a game still running, and its screen, keep running under a pseudonym — `erased:` and 32 random hexadecimal
 *   digits, drawn for this erasure — with the consent to show the result withdrawn;
 * - a screen the member showed as a host is deleted once its game is over, and kept under the pseudonym while it runs;
 * - a booking keeps its seats under the pseudonym, so that the session keeps its count.
 */
final readonly class EscapeRoomPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_escape_room';

    public function __construct(
        private GameStore $games,
        private ResultStore $results,
        private ScreenStore $screens,
        private string $memberKind,
        private ?PartyBookings $bookings = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array<string, non-empty-array<array-key, mixed>>
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);
        $export = [
            'games' => $this->games->ofTeam($member),
            'results' => array_map(static fn (RoomResult $result): array => [
                'room' => $result->room,
                'session' => $result->session,
                'final_seconds' => $result->finalSeconds,
                'played_seconds' => $result->playedSeconds,
                'penalty_seconds' => $result->penaltySeconds,
                'hints' => $result->hints,
                'skips' => $result->skips,
                'escaped_at' => Game::write($result->escapedAt),
                'public' => $result->public,
            ], $this->results->ofTeam($member)),
            'screens' => array_map(static fn (Screen $screen): array => [
                'kind' => $screen->kind->value,
                'shown_at' => Game::write($screen->at),
                'puzzle' => $screen->puzzle,
                'tier' => $screen->tier,
                'message' => $screen->message,
            ], $this->screens->ofHost($member)),
            'bookings' => array_map(static fn (SeatBooking $booking): array => [
                'id' => $booking->id,
                'session' => $booking->session,
                'seats' => $booking->seats,
                'private' => $booking->private,
            ], $this->bookings?->ofParty($member) ?? []),
        ];

        return array_filter($export, static fn (array $rows): bool => [] !== $rows);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);
        $pseudonym = ResourceReference::of(EscapeRooms::ERASED_KIND, sodium_bin2hex(random_bytes(16)));

        $over = array_keys(array_filter($this->games->ofTeam($member), self::isOver(...)));
        $this->games->forget(...$over);
        $this->screens->forget(...$over);
        $running = 0 < $this->games->eraseTeam($member, $pseudonym);
        $this->results->forgetTeam($member);

        $shownOver = array_filter(array_keys($this->screens->ofHost($member)), $this->isGone(...));
        $this->screens->forget(...$shownOver);
        $hosting = 0 < $this->screens->eraseHost($member, $pseudonym);

        $effects = [
            EscapeRoomTables::GAMES.'.team' => self::effect($running),
            EscapeRoomTables::RESULTS.'.team' => self::effect($running),
            EscapeRoomTables::SCREENS.'.game' => self::effect($running),
            EscapeRoomTables::SCREENS.'.host' => self::effect($hosting),
        ];

        if ($this->bookings instanceof PartyBookings) {
            $this->bookings->eraseParty($member, $pseudonym);
            $effects[EscapeRoomTables::BOOKINGS.'.party'] = ErasureEffect::Pseudonymised;
        }

        return ErasureReport::of(self::MODULE, $effects);
    }

    /**
     * Whether a game is over, by the values the library wrote: a game over has the moment it ended.
     *
     * @param array<array-key, mixed> $values
     */
    private static function isOver(array $values): bool
    {
        return null !== ($values['ended_at'] ?? null);
    }

    /**
     * Whether the game of a screen is over, or no longer kept.
     */
    private function isGone(string $game): bool
    {
        $values = $this->games->find($game);

        return null === $values || self::isOver($values);
    }

    /**
     * Deleted when nothing of the member is left running; pseudonymised when a game still runs, which keeps its screen
     * and records its result under the pseudonym.
     */
    private static function effect(bool $running): ErasureEffect
    {
        return $running ? ErasureEffect::Pseudonymised : ErasureEffect::Deleted;
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
