<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\PuzzleBuilder\Exception\InvalidProgress;
use Tilttek\PuzzleBuilder\Progress;

/**
 * One team's game in a room: when it started, its pauses, how it ended, its puzzles and hints, and whether the team
 * agreed to show its result. The library keeps none of it: each move gives a new `Game`, which the application stores
 * as `toArray()` writes it and gives back on the next move. Every time is kept in UTC.
 */
final readonly class Game
{
    private const string FORMAT = 'Y-m-d\TH:i:s.u\Z';

    private const array FIELDS = ['room', 'team', 'session', 'started_at', 'paused_at', 'paused_seconds', 'ended_at', 'outcome', 'progress', 'public'];

    /**
     * @internal made by `EscapeRooms`, read back by `fromArray()`
     */
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public DateTimeImmutable $startedAt,
        public Progress $progress,
        public ?DateTimeImmutable $pausedAt = null,
        public int $pausedSeconds = 0,
        public ?DateTimeImmutable $endedAt = null,
        public ?GameOutcome $outcome = null,
        public bool $public = false,
    ) {
    }

    public function over(): bool
    {
        return $this->outcome instanceof GameOutcome;
    }

    public function paused(): bool
    {
        return $this->pausedAt instanceof DateTimeImmutable;
    }

    /**
     * @internal the move of `EscapeRooms`
     */
    public function with(?Progress $progress = null, ?DateTimeImmutable $pausedAt = null, bool $resume = false, int $pausedSeconds = 0, ?GameOutcome $outcome = null, ?DateTimeImmutable $endedAt = null, ?bool $public = null): self
    {
        return new self(
            $this->room,
            $this->team,
            $this->session,
            $this->startedAt,
            $progress ?? $this->progress,
            $resume ? null : ($pausedAt ?? $this->pausedAt),
            $this->pausedSeconds + $pausedSeconds,
            $endedAt ?? $this->endedAt,
            $outcome ?? $this->outcome,
            $public ?? $this->public,
        );
    }

    /**
     * The game as plain values, to store; `fromArray()` reads them back.
     *
     * @return array{room: string, team: string, session: ?string, started_at: string, paused_at: ?string, paused_seconds: int, ended_at: ?string, outcome: ?string, progress: array<string, mixed>, public: bool}
     */
    public function toArray(): array
    {
        return [
            'room' => $this->room,
            'team' => $this->team->toString(),
            'session' => $this->session,
            'started_at' => self::write($this->startedAt),
            'paused_at' => $this->pausedAt instanceof DateTimeImmutable ? self::write($this->pausedAt) : null,
            'paused_seconds' => $this->pausedSeconds,
            'ended_at' => $this->endedAt instanceof DateTimeImmutable ? self::write($this->endedAt) : null,
            'outcome' => $this->outcome?->value,
            'progress' => $this->progress->toArray(),
            'public' => $this->public,
        ];
    }

    /**
     * Reads back what `toArray()` wrote, checked against the room.
     *
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidRoom when the values are not a game of this room
     */
    public static function fromArray(Room $room, array $values): self
    {
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, self::FIELDS, true)) {
                throw InvalidRoom::because(\sprintf('The field "%s" is not one of a game.', mb_substr((string) $field, 0, 64)));
            }
        }

        $team = $values['team'] ?? null;
        $session = $values['session'] ?? null;
        $paused = $values['paused_seconds'] ?? null;
        $outcome = $values['outcome'] ?? null;
        $progress = $values['progress'] ?? null;
        $public = $values['public'] ?? null;

        if (($values['room'] ?? null) !== $room->id) {
            throw InvalidRoom::because(\sprintf('The game is not one of the room "%s".', $room->id));
        }

        if (!\is_string($team) || (null !== $session && !\is_string($session)) || !\is_int($paused) || $paused < 0 || !\is_bool($public) || !\is_array($progress)) {
            throw InvalidRoom::because('A game holds its team, its session, its paused seconds, its progress and its consent.');
        }

        $ended = self::optionalTime($values['ended_at'] ?? null);
        $outcome = null === $outcome ? null : (\is_string($outcome) ? GameOutcome::tryFrom($outcome) : null) ?? throw InvalidRoom::because('The outcome of a game is escaped, time-up or abandoned.');

        if ((!$ended instanceof DateTimeImmutable) !== (null === $outcome)) {
            throw InvalidRoom::because('A game that ended holds its outcome and the time it ended.');
        }

        try {
            return new self(
                $room->id,
                ResourceReference::fromString($team),
                $session,
                self::read($values['started_at'] ?? null),
                Progress::fromArray($room->scenario, $progress),
                self::optionalTime($values['paused_at'] ?? null),
                $paused,
                $ended,
                $outcome,
                $public,
            );
        } catch (InvalidProgress|InvalidResource $invalid) {
            throw InvalidRoom::because(\sprintf('The game does not read back: %s', $invalid->getMessage()));
        }
    }

    /**
     * @internal
     */
    public static function write(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    private static function optionalTime(mixed $text): ?DateTimeImmutable
    {
        return null === $text ? null : self::read($text);
    }

    private static function read(mixed $text): DateTimeImmutable
    {
        $at = \is_string($text) ? DateTimeImmutable::createFromFormat(self::FORMAT, $text, new DateTimeZone('UTC')) : null;

        if (!$at instanceof DateTimeImmutable || $at->format(self::FORMAT) !== $text) {
            throw InvalidRoom::because('A time is written in UTC to the microsecond, as 2026-11-13T20:15:03.250000Z.');
        }

        return $at;
    }
}
