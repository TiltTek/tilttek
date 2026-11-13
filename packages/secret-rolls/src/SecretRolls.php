<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use DateTimeImmutable;
use Random\Engine\Secure;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\SecretRolls\Contract\JournalPublisher;
use Tilttek\SecretRolls\Contract\SecretRollStore;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Exception\JournalConflict;
use Tilttek\SecretRolls\Exception\SecretRollRefused;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\SceneJournal;
use Tilttek\SecretRolls\Store\NoPublisher;

/**
 * The hidden rolls of a game master, scene by scene.
 *
 * `roll()` rolls out of sight, from a seed of 32 bytes drawn from a secure source, keeps the opening sealed in the
 * store and appends its commitment to the scene's public journal; `reveal()` publishes openings, which any player
 * checks with `RollVerifier`; `close()` ends the scene, revealing the rolls the game master chooses, or all of them,
 * the others staying withheld with their commitments in the journal. Each entry appended is handed to the publisher.
 *
 * The service keeps nothing between calls: the store holds the state.
 */
final readonly class SecretRolls
{
    public function __construct(
        private SecretRollStore $store,
        private JournalPublisher $publisher = new NoPublisher(),
        private DiceRoller $dice = new DiceRoller(),
        private Randomizer $secure = new Randomizer(new Secure()),
    ) {
    }

    /**
     * Rolls out of sight and publishes the commitment of the roll. The game master reads the result in the hidden roll
     * returned; the table sees the entry of its commitment.
     *
     * @param Pool|string $expression the pool, or an input in the notation of the locale
     * @param ?string     $roller     who rolled, an opaque reference the application gives, public
     * @param ?string     $context    what the roll was for, sealed with the result and revealed with it
     *
     * @throws InvalidSecretRoll when the scene, the roller or the context is not one the library takes
     * @throws InvalidExpression when the input does not read
     * @throws LimitExceeded     when the pool goes past the limits of the dice
     * @throws CannotRoll        when the pool cannot be rolled
     * @throws SecretRollRefused when the scene is closed or its journal is full
     * @throws JournalConflict   when another roll or reveal took the position first
     */
    public function roll(string $scene, Pool|string $expression, ?string $roller = null, ?string $context = null, RoundingMode $rounding = RoundingMode::NegativeInfinity, ?string $locale = null, ?DateTimeImmutable $at = null): HiddenRoll
    {
        $journal = $this->open(Encoding::scene($scene));

        if (\count($journal->entries) >= SceneJournal::MAX_ROLLS) {
            throw SecretRollRefused::sceneFull($scene, \count($journal->entries));
        }

        Encoding::text($roller, 'roller', Encoding::MAX_ROLLER);
        Encoding::text($context, 'context', Encoding::MAX_CONTEXT);

        $pool = $expression instanceof Pool ? $expression : $this->dice->parse($expression, $locale);
        $position = $journal->nextPosition();
        $id = Encoding::rollId($scene, $position);
        $seed = Seed::generate($this->secure);
        $result = $this->dice->rollVerifiable($pool, $seed, $id, 0, $rounding)->result;

        $hidden = new HiddenRoll(new RollOpening($id, $scene, $position, $pool->canonical(), $rounding->name, $seed->hex(), $result->toArray(), $context), $roller, $at ?? new DateTimeImmutable());
        $entry = JournalEntry::committed($hidden, $journal->head());
        $this->store->commit($entry, $hidden);
        $this->publisher->publish($entry);

        return $hidden;
    }

    /**
     * Publishes the openings of rolls the scene committed, in the order given.
     *
     * @return list<JournalEntry> the entries appended
     *
     * @throws InvalidSecretRoll when the scene is not one the library takes
     * @throws SecretRollRefused when the scene committed no such roll, revealed it already, or the store lost its
     *                           opening
     * @throws JournalConflict   when another writer took a position first
     */
    public function reveal(string $scene, string ...$rolls): array
    {
        $journal = $this->journal($scene);
        $openings = [];

        foreach (array_unique($rolls) as $roll) {
            if (!$journal->committed($roll) instanceof JournalEntry) {
                throw SecretRollRefused::unknownRoll($scene, $roll);
            }

            if ($journal->isRevealed($roll)) {
                throw SecretRollRefused::alreadyRevealed($roll);
            }

            $openings[] = ($this->store->hidden($roll) ?? throw SecretRollRefused::notSealed($roll))->opening;
        }

        return $this->appendReveals($journal, $openings, new DateTimeImmutable());
    }

    /**
     * Ends the scene: reveals the rolls given — a partial reveal, none when none is given — then closes the journal.
     * The rolls not revealed stay withheld; `reveal()` may still publish them later.
     *
     * @return list<JournalEntry> the entries appended, the closing last
     *
     * @throws InvalidSecretRoll when the scene is not one the library takes
     * @throws SecretRollRefused when the scene is closed already, or a roll given cannot be revealed
     * @throws JournalConflict   when another writer took a position first
     */
    public function close(string $scene, string ...$reveal): array
    {
        $this->open($scene);
        $entries = $this->reveal($scene, ...$reveal);
        $journal = $this->journal($scene);
        $closing = JournalEntry::closed($scene, $journal->nextPosition(), new DateTimeImmutable(), $journal->head());
        $this->store->append($closing);
        $this->publisher->publish($closing);

        return [...$entries, $closing];
    }

    /**
     * Ends the scene and reveals every roll it has not revealed: the total reveal.
     *
     * @return list<JournalEntry> the entries appended, the closing last
     *
     * @throws InvalidSecretRoll when the scene is not one the library takes
     * @throws SecretRollRefused when the scene is closed already, or the store lost an opening
     * @throws JournalConflict   when another writer took a position first
     */
    public function closeRevealingAll(string $scene): array
    {
        return $this->close($scene, ...array_map(static fn (JournalEntry $entry): string => (string) $entry->roll, $this->journal($scene)->withheld()));
    }

    /**
     * The public journal of the scene.
     *
     * @throws InvalidSecretRoll when the scene is not one the library takes
     */
    public function journal(string $scene): SceneJournal
    {
        return new SceneJournal(Encoding::scene($scene), $this->store->entries($scene));
    }

    /**
     * The sealed roll, for the game master's eyes only; null when the store keeps none.
     */
    public function hidden(string $roll): ?HiddenRoll
    {
        return $this->store->hidden($roll);
    }

    /**
     * The journal of a scene still open.
     *
     * @throws SecretRollRefused when the scene is closed
     */
    private function open(string $scene): SceneJournal
    {
        $journal = $this->journal($scene);

        if ($journal->isClosed()) {
            throw SecretRollRefused::sceneClosed($scene);
        }

        return $journal;
    }

    /**
     * @param list<RollOpening> $openings
     *
     * @return list<JournalEntry>
     */
    private function appendReveals(SceneJournal $journal, array $openings, DateTimeImmutable $at): array
    {
        $position = $journal->nextPosition();
        $previous = $journal->head();
        $entries = [];

        foreach ($openings as $opening) {
            $entry = JournalEntry::revealed($opening, $position++, $at, $previous);
            $this->store->append($entry);
            $this->publisher->publish($entry);
            $previous = $entry->hash;
            $entries[] = $entry;
        }

        return $entries;
    }
}
