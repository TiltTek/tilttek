<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Journal;

use JsonException;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\RollCommitment;
use Tilttek\SecretRolls\RollOpening;
use Tilttek\SecretRolls\RollVerifier;

/**
 * The public journal of a scene, in the order of its entries: what the table reads, publishes and checks. Nothing in it
 * is secret: a committed roll shows its commitment only, until an entry reveals its opening.
 */
final readonly class SceneJournal
{
    /** A scene takes a roll while its journal holds fewer entries than this. */
    public const int MAX_ROLLS = 4_999;

    /**
     * The most entries a scene's journal holds, so that reading or checking one stays bounded: each roll committed,
     * each revealed once, and the closing.
     */
    public const int MAX_ENTRIES = 2 * self::MAX_ROLLS + 1;

    /** The longest journal `fromJson()` reads, in bytes. */
    public const int MAX_JSON = 32 * 1024 * 1024;

    /**
     * @param list<JournalEntry> $entries
     */
    public function __construct(
        public string $scene,
        public array $entries,
    ) {
    }

    /**
     * A journal as `toArray()` wrote it, every entry read; whether it holds is `problems()`'s to tell.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidSecretRoll when the data does not read as a journal
     */
    public static function fromArray(array $data): self
    {
        $scene = $data['scene'] ?? null;
        $entries = $data['entries'] ?? null;

        if (!\is_string($scene) || !\is_array($entries) || !array_is_list($entries) || \count($entries) > self::MAX_ENTRIES) {
            throw InvalidSecretRoll::because(\sprintf('A journal holds its scene and a list of at most %d entries.', self::MAX_ENTRIES));
        }

        return new self(Encoding::scene($scene), array_map(static fn (mixed $entry): JournalEntry => \is_array($entry) ? JournalEntry::fromArray($entry) : throw InvalidSecretRoll::because('A journal entry is an object.'), $entries));
    }

    /**
     * A journal as `toJson()` wrote it — the export a player downloads and checks on their own.
     *
     * @throws InvalidSecretRoll when the text is too long, is not JSON, or does not read as a journal
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON) {
            throw InvalidSecretRoll::because(\sprintf('A journal is at most %d bytes of JSON.', self::MAX_JSON));
        }

        try {
            $data = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidSecretRoll::because('A journal is a JSON object.');
        }

        if (!\is_array($data)) {
            throw InvalidSecretRoll::because('A journal is a JSON object.');
        }

        return self::fromArray($data);
    }

    /**
     * The hash of the last entry, which the next one carries: 64 zeros for an empty journal.
     */
    public function head(): string
    {
        return [] === $this->entries ? JournalEntry::GENESIS : array_last($this->entries)->hash;
    }

    /**
     * The position of the next entry.
     */
    public function nextPosition(): int
    {
        return \count($this->entries) + 1;
    }

    public function isClosed(): bool
    {
        return array_any($this->entries, static fn (JournalEntry $entry): bool => EntryKind::Closed === $entry->kind);
    }

    /**
     * The entry that committed the roll, or null when the scene committed no such roll.
     */
    public function committed(string $roll): ?JournalEntry
    {
        return array_find($this->entries, static fn (JournalEntry $entry): bool => EntryKind::Committed === $entry->kind && $entry->roll === $roll);
    }

    public function isRevealed(string $roll): bool
    {
        return array_any($this->entries, static fn (JournalEntry $entry): bool => EntryKind::Revealed === $entry->kind && $entry->roll === $roll);
    }

    /**
     * The openings revealed, by roll.
     *
     * @return array<string, RollOpening>
     */
    public function revealed(): array
    {
        $openings = [];

        foreach ($this->entries as $entry) {
            if ($entry->opening instanceof RollOpening) {
                $openings[$entry->opening->roll] = $entry->opening;
            }
        }

        return $openings;
    }

    /**
     * The entries of the rolls committed and not revealed: hidden still, or withheld once the scene is closed.
     *
     * @return list<JournalEntry>
     */
    public function withheld(): array
    {
        $revealed = $this->revealed();

        return array_values(array_filter($this->entries, static fn (JournalEntry $entry): bool => EntryKind::Committed === $entry->kind && !isset($revealed[(string) $entry->roll])));
    }

    /**
     * What breaks the journal, one sentence per fault, in the order of the entries; none for a journal that holds: the
     * positions follow from 1, each entry carries the hash of the one before and its own, each roll is committed once at
     * the position its identifier names, nothing but a reveal follows the closing, and each opening revealed matches
     * the commitment published before it and rolls again to the result revealed.
     *
     * @return list<string>
     */
    public function problems(RollVerifier $verifier = new RollVerifier()): array
    {
        $problems = [];
        $previous = JournalEntry::GENESIS;
        $committed = [];
        $revealed = [];
        $closed = false;

        foreach ($this->entries as $index => $entry) {
            $position = $index + 1;

            if ($entry->scene !== $this->scene) {
                $problems[] = \sprintf('Entry %d belongs to the scene %s.', $position, $entry->scene);
            }

            if ($entry->position !== $position) {
                $problems[] = \sprintf('Entry %d says it is at position %d.', $position, $entry->position);
            }

            if ($entry->previous !== $previous) {
                $problems[] = \sprintf('Entry %d does not carry the hash of the entry before it.', $position);
            }

            if (!$entry->holds()) {
                $problems[] = \sprintf('Entry %d does not match its hash.', $position);
            }

            $previous = $entry->hash;
            $roll = (string) $entry->roll;

            switch ($entry->kind) {
                case EntryKind::Committed:
                    if ($closed) {
                        $problems[] = \sprintf('Entry %d commits a roll after the scene was closed.', $position);
                    }

                    if (isset($committed[$roll]) || Encoding::rollId($this->scene, $position) !== $roll) {
                        $problems[] = \sprintf('Entry %d commits a roll whose identifier is not its own.', $position);
                    }

                    $committed[$roll] = ['commitment' => (string) $entry->commitment, 'position' => $position];
                    break;
                case EntryKind::Revealed:
                    $problems = [...$problems, ...$this->revealProblems($entry, $position, $committed, $revealed, $verifier)];
                    $revealed[$roll] = $position;
                    break;
                case EntryKind::Closed:
                    if ($closed) {
                        $problems[] = \sprintf('Entry %d closes the scene a second time.', $position);
                    }

                    $closed = true;
                    break;
            }
        }

        return $problems;
    }

    public function holds(RollVerifier $verifier = new RollVerifier()): bool
    {
        return [] === $this->problems($verifier);
    }

    /**
     * @return array{scene: string, entries: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['scene' => $this->scene, 'entries' => array_map(static fn (JournalEntry $entry): array => $entry->toArray(), $this->entries)];
    }

    /**
     * The journal as JSON, in the canonical form its hashes are computed over: what the table publishes or downloads.
     */
    public function toJson(): string
    {
        return Encoding::json($this->toArray());
    }

    /**
     * @param array<string, array{commitment: string, position: int}> $committed the commitments published so far, by roll
     * @param array<string, int>                                      $revealed  the positions of the rolls revealed so far, by roll
     *
     * @return list<string>
     */
    private function revealProblems(JournalEntry $entry, int $position, array $committed, array $revealed, RollVerifier $verifier): array
    {
        $roll = (string) $entry->roll;
        $opening = $entry->opening;

        if (!isset($committed[$roll]) || !$opening instanceof RollOpening) {
            return [\sprintf('Entry %d reveals a roll the scene did not commit before.', $position)];
        }

        $problems = isset($revealed[$roll]) ? [\sprintf('Entry %d reveals a roll revealed already.', $position)] : [];
        if ($opening->scene !== $this->scene || $opening->position !== $committed[$roll]['position']) {
            $problems[] = \sprintf('Entry %d reveals an opening of another scene or position.', $position);
        }

        if ($entry->commitment !== $committed[$roll]['commitment'] || !$verifier->verify($opening, RollCommitment::fromHex($committed[$roll]['commitment']))) {
            $problems[] = \sprintf('Entry %d reveals an opening that does not match the commitment published, or does not roll again to its result.', $position);
        }

        return $problems;
    }
}
