<?php

declare(strict_types=1);

namespace Tilttek\Quests\Duel;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Window;

/**
 * A challenge one member sends another on a duel quest, under the key the application gives it: who challenged whom,
 * when, where it stands, when it was accepted and when it ends, and who won it.
 */
final readonly class Duel
{
    public const int MAX_KEY_LENGTH = 200;

    public function __construct(
        public string $key,
        public string $quest,
        public ResourceReference $challenger,
        public ResourceReference $opponent,
        public DateTimeImmutable $offeredAt,
        public DuelStatus $status = DuelStatus::Offered,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?ResourceReference $winner = null,
        public ?DateTimeImmutable $decidedAt = null,
    ) {
        if ('' === $key || \strlen($key) > self::MAX_KEY_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $key)) {
            throw InvalidQuest::because(\sprintf('A duel key is 1 to %d bytes without whitespace or control characters; "%s" is not.', self::MAX_KEY_LENGTH, $key));
        }

        if ($challenger->equals($opponent)) {
            throw InvalidQuest::because('A member cannot challenge themselves.');
        }
    }

    public function involves(ResourceReference $member): bool
    {
        return $this->challenger->equals($member) || $this->opponent->equals($member);
    }

    /**
     * The time the sides' activity counts, from the acceptance until the end; null before the acceptance.
     */
    public function window(): ?Window
    {
        return $this->acceptedAt instanceof DateTimeImmutable ? new Window($this->acceptedAt, $this->endsAt) : null;
    }

    /**
     * Whether the sides' activity at a moment counts: the duel is accepted, and the moment between its acceptance and
     * its end.
     */
    public function covers(DateTimeImmutable $moment): bool
    {
        return true === $this->window()?->contains($moment);
    }

    public function with(DuelStatus $status, DateTimeImmutable $at, ?ResourceReference $winner = null): self
    {
        return new self($this->key, $this->quest, $this->challenger, $this->opponent, $this->offeredAt, $status, $this->acceptedAt, $this->endsAt, $winner, $at);
    }

    /**
     * The duel accepted again, its winner forgotten: before a rebuild replays the activity that decided it.
     */
    public function reopened(): self
    {
        return new self($this->key, $this->quest, $this->challenger, $this->opponent, $this->offeredAt, DuelStatus::Accepted, $this->acceptedAt, $this->endsAt);
    }

    public function accepted(DateTimeImmutable $at, ?DateTimeImmutable $endsAt): self
    {
        return new self($this->key, $this->quest, $this->challenger, $this->opponent, $this->offeredAt, DuelStatus::Accepted, $at, $endsAt);
    }

    /**
     * @return array{key: string, quest: string, challenger: string, opponent: string, offered_at: string, status: string, accepted_at: ?string, ends_at: ?string, winner: ?string, decided_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'quest' => $this->quest,
            'challenger' => $this->challenger->toString(),
            'opponent' => $this->opponent->toString(),
            'offered_at' => $this->offeredAt->format(DateTimeInterface::ATOM),
            'status' => $this->status->value,
            'accepted_at' => $this->acceptedAt?->format(DateTimeInterface::ATOM),
            'ends_at' => $this->endsAt?->format(DateTimeInterface::ATOM),
            'winner' => $this->winner?->toString(),
            'decided_at' => $this->decidedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
