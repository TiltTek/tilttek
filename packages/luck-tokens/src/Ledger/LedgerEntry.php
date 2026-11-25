<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Ledger;

use DateTimeImmutable;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Instant;

/**
 * One line of the ledger of a table, never changed nor removed: its position, the operation that wrote it, what it
 * records, the kind of token and its holder, the change — positive or negative — and the balance it leaves, who did
 * it and when, in UTC, and the reason or the session given. A session's start has no kind; its holder is the game
 * master who started it. The erasure of a member is the one rewriting: `pseudonymized()` gives the entry with a
 * pseudonym in place of the member.
 */
final readonly class LedgerEntry
{
    public const int MAX_REASON_LENGTH = 280;

    private const array FIELDS = ['table', 'position', 'operation', 'type', 'kind', 'holder', 'change', 'balance', 'actor', 'at', 'reason', 'session'];

    public DateTimeImmutable $at;

    /**
     * @throws InvalidTokenRule when the position is under 1, the balance negative, an identifier or the reason not as
     *                          `LuckTokens` takes them
     */
    public function __construct(
        public ResourceReference $table,
        public int $position,
        public string $operation,
        public EntryType $type,
        public ?string $kind,
        public ResourceReference $holder,
        public int $change,
        public int $balance,
        public ResourceReference $actor,
        DateTimeImmutable $at,
        public ?string $reason = null,
        public ?string $session = null,
    ) {
        if ($position < 1) {
            throw InvalidTokenRule::because(\sprintf('The position of a ledger entry is 1 or more; not %d.', $position));
        }

        if ($balance < 0) {
            throw InvalidTokenRule::because(\sprintf('A balance is never negative; not %d.', $balance));
        }

        if ((EntryType::SessionStarted === $type) !== (null === $kind) || (EntryType::SessionStarted === $type && null === $session)) {
            throw InvalidTokenRule::because('An entry names its kind of token, except the start of a session, which names its session instead.');
        }

        self::identifier($operation, 'An operation');

        if (null !== $session) {
            self::identifier($session, 'A session');
        }

        if (null !== $reason) {
            self::reason($reason);
        }

        $this->at = Instant::utc($at);
    }

    /**
     * @throws InvalidTokenRule when the identifier is not 1 to 64 characters among letters, digits, ".", "_", ":" and "-"
     */
    public static function identifier(string $identifier, string $what): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,64}$/D', $identifier)) {
            throw InvalidTokenRule::because(\sprintf('%s is named by 1 to 64 characters among letters, digits, ".", "_", ":" and "-"; "%s" is not.', $what, mb_substr($identifier, 0, 32)));
        }

        return $identifier;
    }

    /**
     * @throws InvalidTokenRule when the reason is blank, longer than 280 characters or holds control characters
     */
    public static function reason(string $reason): string
    {
        if ('' === trim($reason) || mb_strlen($reason) > self::MAX_REASON_LENGTH || 1 === preg_match('/\p{Cc}/u', $reason)) {
            throw InvalidTokenRule::because(\sprintf('A reason is 1 to %d characters, without control characters.', self::MAX_REASON_LENGTH));
        }

        return $reason;
    }

    /**
     * What the operation asked, to tell the same operation sent again from another one under the same identifier.
     */
    public function intent(): string
    {
        return self::intentOf($this->type, $this->kind, $this->holder, abs($this->change), $this->actor, $this->session);
    }

    /**
     * The intent of an entry not written yet, as `intent()` gives it once written.
     */
    public static function intentOf(EntryType $type, ?string $kind, ResourceReference $holder, int $amount, ResourceReference $actor, ?string $session = null): string
    {
        return implode('|', [$type->value, $kind ?? '', $holder->toString(), $amount, $actor->toString(), $session ?? '']);
    }

    /**
     * The entry with the pseudonym in place of the member, as holder and as actor, and without its reason when the
     * member holds it — a reason is written about the holder; the same entry when it names neither.
     */
    public function pseudonymized(ResourceReference $member, ResourceReference $pseudonym): self
    {
        $holder = $this->holder->equals($member);
        $actor = $this->actor->equals($member);

        if (!$holder && !$actor) {
            return $this;
        }

        return new self(
            $this->table,
            $this->position,
            $this->operation,
            $this->type,
            $this->kind,
            $holder ? $pseudonym : $this->holder,
            $this->change,
            $this->balance,
            $actor ? $pseudonym : $this->actor,
            $this->at,
            $holder ? null : $this->reason,
            $this->session,
        );
    }

    /**
     * @return array{table: string, position: int, operation: string, type: string, kind: string|null, holder: string, change: int, balance: int, actor: string, at: string, reason: string|null, session: string|null}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table->toString(),
            'position' => $this->position,
            'operation' => $this->operation,
            'type' => $this->type->value,
            'kind' => $this->kind,
            'holder' => $this->holder->toString(),
            'change' => $this->change,
            'balance' => $this->balance,
            'actor' => $this->actor->toString(),
            'at' => Instant::write($this->at),
            'reason' => $this->reason,
            'session' => $this->session,
        ];
    }

    /**
     * The entry as `toArray()` wrote it, from the application's storage.
     *
     * @param array<array-key, mixed> $row
     *
     * @throws InvalidTokenRule when the row is not an entry as `toArray()` writes it
     */
    public static function fromArray(array $row): self
    {
        if (self::FIELDS !== array_keys($row)) {
            throw InvalidTokenRule::because(\sprintf('A ledger entry has the fields %s, in that order.', implode(', ', self::FIELDS)));
        }

        $type = \is_string($row['type']) ? EntryType::tryFrom($row['type']) : null;

        if (!$type instanceof EntryType || !\is_int($row['position']) || !\is_int($row['change']) || !\is_int($row['balance'])
            || !\is_string($row['operation']) || !\is_string($row['at'])
            || null !== $row['kind'] && !\is_string($row['kind']) || null !== $row['reason'] && !\is_string($row['reason']) || null !== $row['session'] && !\is_string($row['session'])) {
            throw InvalidTokenRule::because('A ledger entry holds a type, integers for its position, change and balance, texts for its operation and time, and a text or null for its kind, reason and session.');
        }

        return new self(
            self::reference($row['table']),
            $row['position'],
            $row['operation'],
            $type,
            $row['kind'],
            self::reference($row['holder']),
            $row['change'],
            $row['balance'],
            self::reference($row['actor']),
            Instant::read($row['at']),
            $row['reason'],
            $row['session'],
        );
    }

    private static function reference(mixed $reference): ResourceReference
    {
        try {
            return ResourceReference::fromString(\is_string($reference) ? $reference : '');
        } catch (InvalidResource $invalidResource) {
            throw InvalidTokenRule::because($invalidResource->getMessage());
        }
    }
}
