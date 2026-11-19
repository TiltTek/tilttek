<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Beacon;

use Tilttek\Raffle\Encoding;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * One round of a public randomness beacon: its number, its randomness — the SHA-256 of its signature —, its signature,
 * the signature of the round before for a chained scheme, and how it was checked.
 */
final readonly class Beacon
{
    /** The longest signature, in hexadecimal digits. */
    public const int MAX_SIGNATURE = 512;

    /**
     * @throws InvalidRaffle when a value is out of its range, or the randomness is not the SHA-256 of the signature
     */
    public function __construct(
        public int $round,
        public string $randomness,
        public string $signature,
        public ?string $previousSignature,
        public BeaconVerification $verification,
    ) {
        BeaconChain::round($round);
        Encoding::hex($randomness, 'beacon randomness');

        foreach ([$signature, $previousSignature ?? '00'] as $hex) {
            if (1 !== preg_match('/^(?:[0-9a-f]{2}){1,256}$/D', $hex)) {
                throw InvalidRaffle::because(\sprintf('A beacon signature is 2 to %d lower-case hexadecimal digits, by pairs.', self::MAX_SIGNATURE));
            }
        }

        if (!hash_equals(hash('sha256', sodium_hex2bin($signature)), $randomness)) {
            throw InvalidRaffle::because('The randomness of a beacon round is the SHA-256 of its signature.');
        }
    }
}
