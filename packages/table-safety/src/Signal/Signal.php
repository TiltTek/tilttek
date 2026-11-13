<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Signal;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A signal raised at a table during play. An anonymous signal — the default — has no author: nothing, stored or
 * pushed, says who raised it. A named signal carries its author only at a table that chose named signals.
 */
final readonly class Signal
{
    /**
     * @param string $id 32 hexadecimal characters, drawn at random
     */
    public function __construct(
        public string $id,
        public ResourceReference $table,
        public SignalKind $kind,
        public Audience $audience,
        public DateTimeImmutable $raisedAt,
        public ?ResourceReference $raisedBy = null,
        public ?DateTimeImmutable $acknowledgedAt = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidAnswer::because('A signal is identified by 32 hexadecimal characters.');
        }
    }

    public static function newId(): string
    {
        return sodium_bin2hex(random_bytes(16));
    }

    public function isAnonymous(): bool
    {
        return !$this->raisedBy instanceof ResourceReference;
    }

    /**
     * Whether the game master saw the signal and said so.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt instanceof DateTimeImmutable;
    }

    public function acknowledge(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->table, $this->kind, $this->audience, $this->raisedAt, $this->raisedBy, $at);
    }

    /**
     * The signal without its author, as it stays once the author's data is erased.
     */
    public function anonymous(): self
    {
        return new self($this->id, $this->table, $this->kind, $this->audience, $this->raisedAt, null, $this->acknowledgedAt);
    }

    /**
     * What a viewer of the table is told: identifiers, the kind, the audience and the moments — the author's reference
     * only on a named signal.
     *
     * @return array{id: string, table: string, kind: string, audience: string, raised_at: string, raised_by: ?string, acknowledged_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'table' => $this->table->toString(),
            'kind' => $this->kind->value,
            'audience' => $this->audience->value,
            'raised_at' => $this->raisedAt->format(\DATE_ATOM),
            'raised_by' => $this->raisedBy?->toString(),
            'acknowledged_at' => $this->acknowledgedAt?->format(\DATE_ATOM),
        ];
    }
}
