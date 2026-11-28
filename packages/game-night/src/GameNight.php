<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use DateTimeImmutable;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Exception\NightRefused;
use Tilttek\GameNight\Internal\Identifier;
use Tilttek\GameNight\Vote\VoteResult;

/**
 * A game night of a club or a store: when and where, its host, its tables and their seats, the members signed up and
 * those waiting, the games proposed to the vote and the votes. Every change returns a new night, checked whole; the
 * services keep it and say who to tell.
 *
 * The places are the seats of the tables. A sign-up takes one place, two with a guest. The waiting list is served in
 * the order members signed up: a freed place goes to the first one waiting, when their places fit, and to nobody
 * before them.
 */
final readonly class GameNight
{
    public const int MAX_TABLES = 50;

    /** The most places a night seats: its tables, each at its most seats. */
    public const int MAX_PLACES = self::MAX_TABLES * Table::MAX_SEATS;

    public const int MAX_WAITLIST = 200;

    public const int MAX_CANDIDATES = 30;

    /** A night lasts at most a day, in minutes. */
    public const int MAX_MINUTES = 1_440;

    /**
     * @param string                $venue         the identifier of the place, which the application keeps
     * @param string                $host          the member who hosts the night
     * @param list<Table>           $tables        at most 50, each identifier once
     * @param list<Registration>    $registrations in the order members signed up, each member once
     * @param list<string>          $candidates    the games of the catalogue proposed to the vote, at most 30
     * @param array<string, string> $ballots       the game each member voted for, by member
     * @param int                   $version       0 for a night not kept yet
     *
     * @throws InvalidNight when a value is out of its shape or its bounds, or the values do not go together
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $venue,
        public string $host,
        public array $tables = [],
        public NightStatus $status = NightStatus::Draft,
        public array $registrations = [],
        public array $candidates = [],
        public array $ballots = [],
        public int $version = 0,
    ) {
        Identifier::check($id, 'a night');
        Identifier::check($venue, 'a venue');
        Identifier::check($host, 'a member');

        if ($endsAt <= $startsAt) {
            throw InvalidNight::because('A night ends after it starts.');
        }

        if ($this->minutes() > self::MAX_MINUTES) {
            throw InvalidNight::because(\sprintf('A night lasts at most %d minutes.', self::MAX_MINUTES));
        }

        $this->checkTables();
        $this->checkRegistrations();
        $this->checkVotes();
    }

    /**
     * The minutes between the start and the end of the night.
     */
    public function minutes(): int
    {
        return intdiv($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(), 60);
    }

    /**
     * The seats of all the tables.
     */
    public function capacity(): int
    {
        return array_sum(array_map(static fn (Table $table): int => $table->seats, $this->tables));
    }

    /**
     * The places the confirmed sign-ups take, guests included.
     */
    public function confirmedPlaces(): int
    {
        return array_sum(array_map(static fn (Registration $registration): int => $registration->places(), $this->confirmed()));
    }

    public function freeSeats(): int
    {
        return $this->capacity() - $this->confirmedPlaces();
    }

    public function isFull(): bool
    {
        return $this->freeSeats() <= 0;
    }

    public function table(string $id): ?Table
    {
        return array_find($this->tables, static fn (Table $table): bool => $table->id === $id);
    }

    /**
     * The places taken at a table, guests included.
     */
    public function occupancy(string $table): int
    {
        return array_sum(array_map(
            static fn (Registration $registration): int => $registration->places(),
            array_filter($this->registrations, static fn (Registration $registration): bool => $registration->table === $table),
        ));
    }

    public function registration(string $member): ?Registration
    {
        return array_find($this->registrations, static fn (Registration $registration): bool => $registration->member === $member);
    }

    /**
     * The sign-ups that hold their places, in the order members signed up.
     *
     * @return list<Registration>
     */
    public function confirmed(): array
    {
        return array_values(array_filter($this->registrations, static fn (Registration $registration): bool => $registration->isConfirmed()));
    }

    /**
     * The sign-ups waiting for places, the first to be served first.
     *
     * @return list<Registration>
     */
    public function waitlist(): array
    {
        return array_values(array_filter($this->registrations, static fn (Registration $registration): bool => !$registration->isConfirmed()));
    }

    /**
     * The members who were waiting on `$before` and hold their places now, in the order they signed up.
     *
     * @return list<string>
     */
    public function promotedSince(self $before): array
    {
        $waiting = array_map(static fn (Registration $registration): string => $registration->member, $before->waitlist());

        return array_values(array_map(
            static fn (Registration $registration): string => $registration->member,
            array_filter($this->confirmed(), static fn (Registration $registration): bool => \in_array($registration->member, $waiting, true)),
        ));
    }

    /**
     * The night with a table added, or replacing the table of the same identifier; members waiting take the seats it
     * adds.
     *
     * @throws NightRefused when the night is cancelled, or the table has fewer seats than the places taken at it
     */
    public function withTable(Table $table): self
    {
        $this->refuseWhenCancelled();

        if ($table->seats < $this->occupancy($table->id)) {
            throw NightRefused::because(\sprintf('The table %s seats more places than %d.', $table->id, $table->seats));
        }

        $tables = $this->tables;
        $index = array_find_key($tables, static fn (Table $kept): bool => $kept->id === $table->id);

        if (null === $index) {
            $tables[] = $table;
        } else {
            $tables[$index] = $table;
        }

        return $this->with(tables: $tables)->served();
    }

    /**
     * The night without a table; the members seated at it are seated nowhere.
     *
     * @throws NightRefused when the night is cancelled, the table is not one of the night, or the other tables do
     *                      not seat the confirmed places
     */
    public function withoutTable(string $id): self
    {
        $this->refuseWhenCancelled();

        if (!$this->table($id) instanceof Table) {
            throw NightRefused::because(\sprintf('The table %s is not one of the night.', $id));
        }

        $tables = array_values(array_filter($this->tables, static fn (Table $table): bool => $table->id !== $id));

        if (array_sum(array_map(static fn (Table $table): int => $table->seats, $tables)) < $this->confirmedPlaces()) {
            throw NightRefused::because(\sprintf('Without the table %s, the night seats fewer places than are confirmed.', $id));
        }

        return $this->with(
            tables: $tables,
            registrations: array_map(
                static fn (Registration $registration): Registration => $registration->table === $id ? $registration->seatedAt(null) : $registration,
                $this->registrations,
            ),
        );
    }

    /**
     * @throws NightRefused when the night is not a draft, or has no seat
     */
    public function published(): self
    {
        if (NightStatus::Draft !== $this->status) {
            throw NightRefused::because('Only a draft night is published.');
        }

        if (0 === $this->capacity()) {
            throw NightRefused::because('A night is published with at least one table.');
        }

        return $this->with(status: NightStatus::Published);
    }

    /**
     * @throws NightRefused when the night is already cancelled
     */
    public function cancelled(): self
    {
        $this->refuseWhenCancelled();

        return $this->with(status: NightStatus::Cancelled);
    }

    /**
     * The night with the member signed up: confirmed when their places are free and nobody waits, waiting otherwise.
     *
     * @throws NightRefused when the night is not published, the member is signed up, or the waiting list is full
     */
    public function registered(string $member, bool $guest = false): self
    {
        $this->refuseUnlessPublished();

        if ($this->registration($member) instanceof Registration) {
            throw NightRefused::because(\sprintf('The member %s is signed up for the night.', $member));
        }

        $registration = new Registration($member, $guest);

        if ([] !== $this->waitlist() || $registration->places() > $this->freeSeats()) {
            if (\count($this->waitlist()) >= self::MAX_WAITLIST) {
                throw NightRefused::because(\sprintf('The night is full, and its waiting list holds %d members.', self::MAX_WAITLIST));
            }

            $registration = new Registration($member, $guest, RegistrationStatus::Waitlisted);
        }

        return $this->with(registrations: [...$this->registrations, $registration]);
    }

    /**
     * The night without the member's sign-up and vote; the places they free go to the members waiting.
     *
     * @throws NightRefused when the night is not published, or the member is not signed up
     */
    public function withdrawn(string $member): self
    {
        $this->refuseUnlessPublished();
        $this->signedUp($member);

        $ballots = $this->ballots;
        unset($ballots[$member]);
        $kept = [];

        foreach ($this->registrations as $registration) {
            if ($registration->member !== $member) {
                $kept[] = $registration;
            }
        }

        return $this->with(registrations: $kept, ballots: $ballots)->served();
    }

    /**
     * The night with the member, and their guest, seated at a table, or seated nowhere with `null`.
     *
     * @throws NightRefused when the night is not published, the member is not confirmed, the table is not one of the
     *                      night, or it has not the free seats
     */
    public function seated(string $member, ?string $table): self
    {
        $this->refuseUnlessPublished();
        $registration = $this->signedUp($member);

        if (!$registration->isConfirmed()) {
            throw NightRefused::because(\sprintf('The member %s waits for places, and is seated once they hold them.', $member));
        }

        if (null !== $table) {
            $seats = $this->table($table)->seats ?? throw NightRefused::because(\sprintf('The table %s is not one of the night.', $table));

            if ($registration->table !== $table && $this->occupancy($table) + $registration->places() > $seats) {
                throw NightRefused::because(\sprintf('The table %s has not the free seats.', $table));
            }
        }

        return $this->with(registrations: array_map(
            static fn (Registration $kept): Registration => $kept->member === $member ? $kept->seatedAt($table) : $kept,
            $this->registrations,
        ));
    }

    /**
     * The night with the games proposed to the vote; the votes for a game no longer proposed are dropped.
     *
     * @param list<string> $games the identifiers of the games in the catalogue, at most 30, each once
     *
     * @throws InvalidNight when an identifier is not one, a game is there twice, or there are too many
     * @throws NightRefused when the night is cancelled
     */
    public function withCandidates(array $games): self
    {
        $this->refuseWhenCancelled();

        return $this->with(
            candidates: $games,
            ballots: array_filter($this->ballots, static fn (string $game): bool => \in_array($game, $games, true)),
        );
    }

    /**
     * The night with the member's vote for a game proposed, in place of their earlier vote.
     *
     * @throws NightRefused when the night is not published, the member is not signed up, or the game is not proposed
     */
    public function voted(string $member, string $game): self
    {
        $this->refuseUnlessPublished();
        $this->signedUp($member);

        if (!\in_array($game, $this->candidates, true)) {
            throw NightRefused::because(\sprintf('The game %s is not proposed to the vote.', $game));
        }

        return $this->with(ballots: [...$this->ballots, $member => $game]);
    }

    /**
     * @throws NightRefused when the night is not published
     */
    public function unvoted(string $member): self
    {
        $this->refuseUnlessPublished();

        $ballots = $this->ballots;
        unset($ballots[$member]);

        return $this->with(ballots: $ballots);
    }

    public function voteResult(): VoteResult
    {
        return VoteResult::count($this->candidates, $this->ballots);
    }

    public function withVersion(int $version): self
    {
        return $this->with(version: $version);
    }

    /**
     * @param ?list<Table>           $tables
     * @param ?list<Registration>    $registrations
     * @param ?list<string>          $candidates
     * @param ?array<string, string> $ballots
     */
    private function with(
        ?array $tables = null,
        ?NightStatus $status = null,
        ?array $registrations = null,
        ?array $candidates = null,
        ?array $ballots = null,
        ?int $version = null,
    ): self {
        return new self(
            $this->id,
            $this->startsAt,
            $this->endsAt,
            $this->venue,
            $this->host,
            $tables ?? $this->tables,
            $status ?? $this->status,
            $registrations ?? $this->registrations,
            $candidates ?? $this->candidates,
            $ballots ?? $this->ballots,
            $version ?? $this->version,
        );
    }

    /**
     * The night with the members waiting confirmed, the first first, as long as their places fit.
     */
    private function served(): self
    {
        $free = $this->freeSeats();
        $serving = true;
        $registrations = [];

        foreach ($this->registrations as $registration) {
            if ($serving && !$registration->isConfirmed()) {
                $serving = $registration->places() <= $free;

                if ($serving) {
                    $free -= $registration->places();
                    $registration = $registration->confirmed();
                }
            }

            $registrations[] = $registration;
        }

        return $this->with(registrations: $registrations);
    }

    /**
     * The sign-up of a member of the night.
     *
     * @throws NightRefused when the member is not signed up
     */
    public function signedUp(string $member): Registration
    {
        return $this->registration($member) ?? throw NightRefused::because(\sprintf('The member %s is not signed up for the night.', $member));
    }

    private function refuseWhenCancelled(): void
    {
        if (NightStatus::Cancelled === $this->status) {
            throw NightRefused::because('The night is cancelled.');
        }
    }

    private function refuseUnlessPublished(): void
    {
        if (NightStatus::Published !== $this->status) {
            throw NightRefused::because('The night is not open to sign-ups.');
        }
    }

    private function checkTables(): void
    {
        if (\count($this->tables) > self::MAX_TABLES) {
            throw InvalidNight::because(\sprintf('A night has at most %d tables.', self::MAX_TABLES));
        }

        $ids = array_map(static fn (Table $table): string => $table->id, $this->tables);

        if (\count(array_unique($ids)) !== \count($ids)) {
            throw InvalidNight::because('A night has each table once.');
        }
    }

    private function checkRegistrations(): void
    {
        $members = array_map(static fn (Registration $registration): string => $registration->member, $this->registrations);

        if (\count(array_unique($members)) !== \count($members)) {
            throw InvalidNight::because('A night has each member signed up once.');
        }

        if ($this->freeSeats() < 0) {
            throw InvalidNight::because('The confirmed places of a night fit in its seats.');
        }

        if (\count($this->waitlist()) > self::MAX_WAITLIST) {
            throw InvalidNight::because(\sprintf('The waiting list of a night holds at most %d members.', self::MAX_WAITLIST));
        }

        $occupancy = [];

        foreach ($this->registrations as $registration) {
            if (null === $registration->table) {
                continue;
            }

            if (!$registration->isConfirmed() || !$this->table($registration->table) instanceof Table) {
                throw InvalidNight::because('A member seated at a table holds their places at a table of the night.');
            }

            $occupancy[$registration->table] = ($occupancy[$registration->table] ?? 0) + $registration->places();
        }

        foreach ($this->tables as $table) {
            if (isset($occupancy[$table->id]) && $occupancy[$table->id] > $table->seats) {
                throw InvalidNight::because(\sprintf('The places taken at the table %s fit in its seats.', $table->id));
            }
        }
    }

    private function checkVotes(): void
    {
        if (\count($this->candidates) > self::MAX_CANDIDATES) {
            throw InvalidNight::because(\sprintf('A night proposes at most %d games to the vote.', self::MAX_CANDIDATES));
        }

        foreach ($this->candidates as $game) {
            Identifier::game($game);
        }

        if (\count(array_unique($this->candidates)) !== \count($this->candidates)) {
            throw InvalidNight::because('A night proposes each game once.');
        }

        $members = array_flip(array_map(static fn (Registration $registration): string => $registration->member, $this->registrations));

        foreach ($this->ballots as $member => $game) {
            if (!\in_array($game, $this->candidates, true) || !isset($members[$member])) {
                throw InvalidNight::because('A vote is a member signed up for the night voting for a game proposed.');
            }
        }
    }
}
