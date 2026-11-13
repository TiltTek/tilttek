<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;

/**
 * Checks a revealed roll, as any player can: the opening hashes to the commitment published when the roll was made, its
 * expression is in the canonical form, and rolling it again with its seed, its roll as the client seed and its rounding
 * gives the result revealed. The expression is read within the limits of the `DiceRoller` given, so that a forged
 * opening costs no more than an honest one.
 */
final readonly class RollVerifier
{
    public function __construct(
        private DiceRoller $dice = new DiceRoller(),
    ) {
    }

    public function verify(RollOpening $opening, RollCommitment $published): bool
    {
        if (!$published->equals($opening->commitment())) {
            return false;
        }

        $rounding = self::rounding($opening->rounding);

        if (!$rounding instanceof RoundingMode) {
            return false;
        }

        try {
            $pool = $this->dice->parse($opening->expression);

            if ($pool->canonical() !== $opening->expression) {
                return false;
            }

            $roll = $this->dice->rollVerifiable($pool, Seed::fromHex($opening->seed), $opening->roll, 0, $rounding);
        } catch (DiceException) {
            return false;
        }

        return Encoding::json($roll->result->toArray()) === Encoding::json($opening->result);
    }

    /**
     * Whether an opening, as published, checks out against a commitment, as published: false for data that does not
     * read as an opening or a commitment.
     *
     * @param array<array-key, mixed> $opening
     */
    public function verifyPublished(array $opening, string $commitment): bool
    {
        try {
            return $this->verify(RollOpening::fromArray($opening), RollCommitment::fromHex($commitment));
        } catch (InvalidSecretRoll) {
            return false;
        }
    }

    /**
     * The rounding of this name, or null when PHP knows none by it.
     */
    public static function rounding(string $name): ?RoundingMode
    {
        return array_find(RoundingMode::cases(), static fn (RoundingMode $mode): bool => $mode->name === $name);
    }
}
