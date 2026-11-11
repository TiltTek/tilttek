<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Dialect\Translation;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Fairness\VerifiableRoll;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\DiceBundle\Application\Exception\UnknownRoll;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;

/**
 * The dice of the application: inputs parsed in the reader's notation or read from Rolisteam's DiceParser, pools written in a language or a table's
 * dialect, plain rolls from a secure source, replayable rolls kept in the journal and replayed from it, verifiable
 * rolls by commitment, and the appearance of a member's dice. Each roll takes the rounding of its divisions — down by
 * default; up for one side and down for the other when a game says so —, which a replay and a verification keep.
 */
final readonly class DiceRolls
{
    public function __construct(
        private DiceRoller $roller,
        private Randomizer $secure,
        private RecordedRollRepository $rolls,
        private ClockInterface $clock,
        private DiceAppearance $appearance = new NoDiceAppearance(),
    ) {
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function parse(string $input, ?string $locale = null): Pool
    {
        return $this->roller->parse($input, $locale);
    }

    /**
     * A command written in the notation of Rolisteam's DiceParser.
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function readRolisteam(string $input): Pool
    {
        return $this->roller->readRolisteam($input);
    }

    public function write(Pool $pool, ?string $locale = null): string
    {
        return $this->roller->write($pool, $locale);
    }

    /**
     * @param RoundingMode $rounding how the roll rounds its divisions: the dialect gives a notice when it is not down
     *
     * @throws InvalidDefinition when no dialect has this name
     */
    public function translate(Pool $pool, string $dialect, RoundingMode $rounding = RoundingMode::NegativeInfinity): Translation
    {
        return $this->roller->translate($pool, $dialect, $rounding);
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function roll(Pool|string $pool, ?string $locale = null, RoundingMode $rounding = RoundingMode::NegativeInfinity): PoolResult
    {
        return $this->roller->roll($this->pool($pool, $locale), $this->secure, $rounding);
    }

    /**
     * Rolls and keeps the roll in the journal, to be replayed by its identifier.
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function rollReplayable(Pool|string $pool, ?ResourceReference $owner = null, ?string $locale = null, RoundingMode $rounding = RoundingMode::NegativeInfinity): RecordedRoll
    {
        $roll = $this->roller->rollReplayable($this->pool($pool, $locale), $this->secure, $rounding);
        $recorded = RecordedRoll::record($roll, $owner, $this->clock->now());
        $this->rolls->save($recorded);

        return $recorded;
    }

    /**
     * Rolls a recorded roll again, from its expression, its seed and its rounding.
     *
     * @throws UnknownRoll when the journal holds no roll with this identifier
     */
    public function replay(string $id): Replay
    {
        $recorded = $this->rolls->get($id) ?? throw UnknownRoll::withId($id);

        return new Replay($recorded, $this->roller->replay($recorded->id(), $this->roller->parse($recorded->expression()), $recorded->seed(), $recorded->rounding())->result);
    }

    /**
     * A new seed for verifiable rolls: publish its commitment, keep the seed secret until the rolls are done.
     */
    public function newSeed(): Seed
    {
        return Seed::generate($this->secure);
    }

    /**
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function rollVerifiable(Pool|string $pool, Seed $seed, string $clientSeed = '', int $nonce = 0, ?string $locale = null, RoundingMode $rounding = RoundingMode::NegativeInfinity): VerifiableRoll
    {
        return $this->roller->rollVerifiable($this->pool($pool, $locale), $seed, $clientSeed, $nonce, $rounding);
    }

    /**
     * @param list<array<string, mixed>> $claimed  the published result
     * @param RoundingMode               $rounding the rounding the roll was made with, as it was published
     */
    public function verify(Commitment $commitment, Seed $seed, string $clientSeed, int $nonce, Pool|string $pool, array $claimed, RoundingMode $rounding = RoundingMode::NegativeInfinity): bool
    {
        return $this->roller->fairRoller()->verify($commitment, $seed, $clientSeed, $nonce, $this->pool($pool, null), $claimed, $rounding);
    }

    /**
     * The rolls of an owner in the journal, the latest first.
     *
     * @return list<RecordedRoll>
     */
    public function rollsOf(ResourceReference $owner, int $limit): array
    {
        return $this->rolls->ofOwner($owner, $limit);
    }

    /**
     * Erases an owner's rolls from the journal, when the person is erased.
     */
    public function eraseOwner(ResourceReference $owner): int
    {
        return $this->rolls->eraseOwner($owner);
    }

    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        return $this->appearance->appearance($member, $sides);
    }

    private function pool(Pool|string $pool, ?string $locale): Pool
    {
        return $pool instanceof Pool ? $pool : $this->roller->parse($pool, $locale);
    }
}
