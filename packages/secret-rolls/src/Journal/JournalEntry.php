<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Journal;

use DateTimeImmutable;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Instant;
use Tilttek\SecretRolls\RollOpening;

/**
 * One entry of a scene's journal, public: a roll committed, a roll revealed, or the scene closed.
 *
 * Each entry carries the hash of the one before it — 64 zeros for the first — and its own hash, the SHA-256 of the
 * canonical JSON of `["tilttek-secret-rolls/1", scene, position, kind, roll, commitment, roller, opening, at,
 * previous]`: an entry removed, changed or moved breaks every hash after it.
 */
final readonly class JournalEntry
{
    /** The hash before the first entry. */
    public const string GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public string $hash;

    private function __construct(
        public string $scene,
        public int $position,
        public EntryKind $kind,
        public ?string $roll,
        public ?string $commitment,
        public ?string $roller,
        public ?RollOpening $opening,
        public DateTimeImmutable $at,
        public string $previous,
        ?string $hash = null,
    ) {
        $this->hash = $hash ?? $this->computeHash();
    }

    /**
     * The entry that publishes the commitment of a hidden roll, at the position its opening names.
     */
    public static function committed(HiddenRoll $roll, string $previous): self
    {
        return new self($roll->scene(), $roll->opening->position, EntryKind::Committed, $roll->roll(), $roll->commitment()->hex, $roll->roller, null, $roll->rolledAt, $previous);
    }

    /**
     * The entry that publishes the opening of a roll committed earlier.
     */
    public static function revealed(RollOpening $opening, int $position, DateTimeImmutable $at, string $previous): self
    {
        return new self($opening->scene, $position, EntryKind::Revealed, $opening->roll, $opening->commitment()->hex, null, $opening, self::utc($at), $previous);
    }

    public static function closed(string $scene, int $position, DateTimeImmutable $at, string $previous): self
    {
        return new self($scene, $position, EntryKind::Closed, null, null, null, null, self::utc($at), $previous);
    }

    /**
     * An entry as `toArray()` wrote it, its hash kept as written: `SceneJournal` tells whether it holds.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidSecretRoll when the data does not read as an entry
     */
    public static function fromArray(array $data): self
    {
        $kind = \is_string($data['kind'] ?? null) ? EntryKind::tryFrom($data['kind']) : null;
        $scene = $data['scene'] ?? null;
        $position = $data['position'] ?? null;
        $roll = $data['roll'] ?? null;
        $commitment = $data['commitment'] ?? null;
        $roller = $data['roller'] ?? null;
        $opening = $data['opening'] ?? null;
        $at = $data['at'] ?? null;
        $previous = $data['previous'] ?? null;
        $hash = $data['hash'] ?? null;

        if (!$kind instanceof EntryKind || !\is_string($scene) || !\is_int($position) || $position < 1 || !\is_string($at) || !\is_string($previous) || !\is_string($hash)) {
            throw InvalidSecretRoll::because('A journal entry holds a kind, a scene, a position of 1 or more, a time, the previous hash and its hash.');
        }

        $expected = match ($kind) {
            EntryKind::Committed => \is_string($roll) && \is_string($commitment) && (null === $roller || \is_string($roller)) && null === $opening,
            EntryKind::Revealed => \is_string($roll) && \is_string($commitment) && null === $roller && \is_array($opening),
            EntryKind::Closed => null === $roll && null === $commitment && null === $roller && null === $opening,
        };

        if (!$expected) {
            throw InvalidSecretRoll::because(\sprintf('A %s entry does not hold these fields.', $kind->value));
        }

        /** @var ?string $roll */
        /** @var ?string $commitment */
        /** @var ?string $roller */
        if (null !== $roll && 1 !== preg_match('/^[0-9a-f]{32}$/D', $roll)) {
            throw InvalidSecretRoll::because('The roll of an entry is 32 lower-case hexadecimal digits.');
        }

        return new self(
            Encoding::scene($scene),
            $position,
            $kind,
            $roll,
            null === $commitment ? null : Encoding::hex64($commitment, 'commitment'),
            Encoding::text($roller, 'roller', Encoding::MAX_ROLLER),
            \is_array($opening) ? RollOpening::fromArray($opening) : null,
            Instant::read($at),
            Encoding::hex64($previous, 'previous hash'),
            Encoding::hex64($hash, 'hash'),
        );
    }

    /**
     * Whether the hash written is the hash of the entry.
     */
    public function holds(): bool
    {
        return hash_equals($this->computeHash(), $this->hash);
    }

    /**
     * @return array{scene: string, position: int, kind: string, roll: ?string, commitment: ?string, roller: ?string, opening: ?array<string, mixed>, at: string, previous: string, hash: string}
     */
    public function toArray(): array
    {
        return [
            'scene' => $this->scene,
            'position' => $this->position,
            'kind' => $this->kind->value,
            'roll' => $this->roll,
            'commitment' => $this->commitment,
            'roller' => $this->roller,
            'opening' => $this->opening?->toArray(),
            'at' => Instant::write($this->at),
            'previous' => $this->previous,
            'hash' => $this->hash,
        ];
    }

    private function computeHash(): string
    {
        return Encoding::hash([
            Encoding::VERSION,
            $this->scene,
            $this->position,
            $this->kind->value,
            $this->roll,
            $this->commitment,
            $this->roller,
            $this->opening?->toArray(),
            Instant::write($this->at),
            $this->previous,
        ]);
    }

    private static function utc(DateTimeImmutable $at): DateTimeImmutable
    {
        return Instant::read(Instant::write($at));
    }
}
