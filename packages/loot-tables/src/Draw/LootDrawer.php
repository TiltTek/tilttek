<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

use RoundingMode;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootBox;

/**
 * Draws a box from a committed seed, through the verifiable rolls of `tilttek/dice`, and verifies a published draw.
 *
 * The seed of a `PendingDraw` is fixed, and its commitment published, before anyone knows what the draw gives; the
 * buyer then gives a client seed of their own; the reveal rolls the dice of the box from both, one per table; the
 * proof lets anyone roll them again and compare. Whoever drew the seed cannot choose the result only as long as they
 * did not know the client seed when committing: a client seed the shop knows or chooses — an order number, a line —
 * gives no fairness at all.
 */
final readonly class LootDrawer
{
    public function __construct(
        private FairRoller $roller = new FairRoller(),
    ) {
    }

    /**
     * The draw of the box from the pending draw's seed and the buyer's client seed.
     *
     * @param string $clientSeed 1 to 256 bytes of UTF-8 text, given by the buyer after the commitment was shown to them
     *
     * @throws InvalidLoot when the client seed is empty, longer than 256 bytes or not UTF-8 text
     */
    public function reveal(LootBox $box, PendingDraw $pending, string $clientSeed): RevealedDraw
    {
        $roll = $this->roller->roll($box->pool(), $pending->seed, PendingDraw::clientSeed($clientSeed), $pending->nonce);
        /** @var list<int> $faces a single die of numbers always gives a total */
        $faces = $roll->result->totals();

        return new RevealedDraw(LootResult::ofFaces($box, $faces, DrawSource::Verifiable), $roll);
    }

    /**
     * Whether a published proof is an honest draw of the box: the seed matches the commitment, rolling the dice of the
     * box again gives the published roll, and the faces are those of the roll. A proof that does not read is false.
     *
     * @param array<array-key, mixed> $proof as `RevealedDraw::proof()` wrote it
     */
    public function verify(LootBox $box, array $proof): bool
    {
        $pool = $box->pool();

        if (($proof['box'] ?? null) !== $box->key
            || ($proof['expression'] ?? null) !== $pool->canonical()
            || ($proof['rounding'] ?? null) !== RoundingMode::NegativeInfinity->name
            || !\is_string($proof['commitment'] ?? null)
            || !\is_string($proof['seed'] ?? null)
            || !\is_string($proof['client_seed'] ?? null)
            || !\is_int($proof['nonce'] ?? null)
            || !\is_array($proof['result'] ?? null)
            || !array_is_list($proof['result'])
            || ($proof['faces'] ?? null) !== array_column($proof['result'], 'total')) {
            return false;
        }

        /** @var list<array<string, mixed>> $claimed compared as a whole with the roll made again */
        $claimed = $proof['result'];

        try {
            return $this->roller->verify(Commitment::fromHex($proof['commitment']), Seed::fromHex($proof['seed']), $proof['client_seed'], $proof['nonce'], $pool, $claimed);
        } catch (DiceException) {
            return false;
        }
    }
}
