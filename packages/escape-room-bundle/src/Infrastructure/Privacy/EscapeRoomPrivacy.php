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
 * last as a host, the bookings they made as the party. The erasure writes one pseudonym of its own — `erased:` and 32
 * random hexadecimal digits — in place of the member everywhere, and withdraws their results from the leaderboard;
 * the times, the hints and the seats stay, so the other teams keep their places and the sessions their seats.
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
        $this->games->eraseTeam($member, $pseudonym);
        $this->results->eraseTeam($member, $pseudonym);
        $this->screens->eraseHost($member, $pseudonym);
        $effects = [
            EscapeRoomTables::GAMES.'.team' => ErasureEffect::Pseudonymised,
            EscapeRoomTables::RESULTS.'.team' => ErasureEffect::Pseudonymised,
            EscapeRoomTables::SCREENS.'.host' => ErasureEffect::Pseudonymised,
        ];

        if ($this->bookings instanceof PartyBookings) {
            $this->bookings->eraseParty($member, $pseudonym);
            $effects[EscapeRoomTables::BOOKINGS.'.party'] = ErasureEffect::Pseudonymised;
        }

        return ErasureReport::of(self::MODULE, $effects);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
