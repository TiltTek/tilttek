<?php

declare(strict_types=1);

namespace Tilttek\Dice;

use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Dialect\Dialects;
use Tilttek\Dice\Dialect\Translation;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Fairness\VerifiableRoll;
use Tilttek\Dice\Notation\Notations;
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Reader\RolisteamReader;
use Tilttek\Dice\Replay\ReplayableRoll;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Rolling\Roller;

/**
 * The library in one object: parse an input in a language's notation or a command of Rolisteam's DiceParser, with the
 * macros it names, write a pool in a language's notation or in a table's dialect, and roll it — plainly, verifiably or to be replayed — within the limits and with the custom dice
 * of the application, its divisions rounded as the caller says.
 */
final readonly class DiceRoller
{
    private Roller $roller;

    public function __construct(
        private Limits $limits = new Limits(),
        CustomDice $customDice = new CustomDice(),
        private Notations $notations = new Notations(),
        private Dialects $dialects = new Dialects(),
    ) {
        $this->roller = new Roller($limits, $customDice);
    }

    /**
     * @param ?string $locale the language whose notation the input is written in; the canonical one when null
     * @param ?Macros $macros the macros the input may name, `$name`; none when null
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function parse(string $input, ?string $locale = null, ?Macros $macros = null): Pool
    {
        return new DiceParser($this->limits, $this->notations->for($locale), $macros)->parse($input);
    }

    /**
     * A command written in the notation of Rolisteam's DiceParser, read into the library's tree.
     *
     * @param ?Macros $macros the macros the command may name, `$name`; none when null
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function readRolisteam(string $input, ?Macros $macros = null): Pool
    {
        return new RolisteamReader($this->limits)->read($input, $macros);
    }

    /**
     * The pool in a language's notation; the canonical form when the locale is null.
     */
    public function write(Pool $pool, ?string $locale = null): string
    {
        return new NotationWriter($this->notations->for($locale))->write($pool);
    }

    /**
     * @param RoundingMode $rounding how the roll rounds its divisions: the dialect writes them rounded down, and gives
     *                               a notice when the roll rounds them another way
     *
     * @throws InvalidDefinition when no dialect has this name
     */
    public function translate(Pool $pool, string $dialect, RoundingMode $rounding = RoundingMode::NegativeInfinity): Translation
    {
        return $this->dialects->get($dialect)->translate($pool, $rounding);
    }

    /**
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function roll(Pool $pool, Randomizer $randomizer, RoundingMode $rounding = RoundingMode::NegativeInfinity): PoolResult
    {
        return $this->roller->roll($pool, $randomizer, $rounding);
    }

    /**
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function rollVerifiable(Pool $pool, Seed $seed, string $clientSeed = '', int $nonce = 0, RoundingMode $rounding = RoundingMode::NegativeInfinity): VerifiableRoll
    {
        return new FairRoller($this->roller)->roll($pool, $seed, $clientSeed, $nonce, $rounding);
    }

    /**
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function rollReplayable(Pool $pool, Randomizer $secure, RoundingMode $rounding = RoundingMode::NegativeInfinity): ReplayableRoll
    {
        return new Replayer($this->roller)->roll($pool, $secure, $rounding);
    }

    public function replay(string $id, Pool $pool, Seed $seed, RoundingMode $rounding = RoundingMode::NegativeInfinity): ReplayableRoll
    {
        return new Replayer($this->roller)->replay($id, $pool, $seed, $rounding);
    }

    public function fairRoller(): FairRoller
    {
        return new FairRoller($this->roller);
    }
}
