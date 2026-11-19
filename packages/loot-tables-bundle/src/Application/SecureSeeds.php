<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\LootTables\Draw\PendingDraw;

/**
 * The seeds of the draws, drawn here from the operating system's cryptographic generator: `Random\Engine\Secure` is
 * the only engine this class takes, and PHP declares it final, so no other source can stand in for it.
 *
 * Each seed is 32 bytes; its commitment, the SHA-256 of those bytes, is shown before the payment, and the seed itself
 * only once the draw is revealed.
 */
final readonly class SecureSeeds
{
    /** The length of a seed, in bytes. */
    public const int BYTES = 32;

    public function __construct(
        private Secure $engine = new Secure(),
    ) {
    }

    /**
     * A new draw, its seed drawn from the cryptographic generator.
     */
    public function open(): PendingDraw
    {
        return PendingDraw::restore(sodium_bin2hex(new Randomizer($this->engine)->getBytes(self::BYTES)));
    }
}
