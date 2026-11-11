<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use Phil\Resource\ResourceReference;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A pack committed and not yet opened: the key it will be opened under, the member, the pack, and the secret seed of
 * the draw in hexadecimal, whose commitment the site shows before the member gives a client seed of their own.
 *
 * The seed stays with the site until the opening; whoever learns it before then knows every draw a client seed gives.
 */
final readonly class PendingOpening
{
    public function __construct(
        public string $key,
        public ResourceReference $member,
        public string $pack,
        public string $seed,
    ) {
        if (1 !== preg_match('/^(?:[0-9a-f]{2}){16,64}$/D', $seed)) {
            throw InvalidCollection::because('The seed of an opening is 16 to 64 bytes in lower-case hexadecimal.');
        }
    }

    /**
     * The SHA-256 of the seed, in hexadecimal: shown to the member before they give their client seed.
     */
    public function commitment(): string
    {
        return Packs::commitment(sodium_hex2bin($this->seed));
    }
}
