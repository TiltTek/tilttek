<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

use Tilttek\Heraldry\Exception\InvalidCoat;

/**
 * One to three charges of the same kind and tincture, and where they lie: on the field, or on the ordinary.
 */
final readonly class ChargeGroup
{
    /** The most charges of a group. */
    public const int MAX_COUNT = 3;

    /**
     * @param bool $onPiece whether the charges lie on the ordinary rather than on the field
     *
     * @throws InvalidCoat when the count is outside 1 to 3, or a single charge is given an arrangement
     */
    public function __construct(
        public Charge $charge,
        public Tincture $tincture,
        public int $count = 1,
        public Arrangement $arrangement = Arrangement::Usual,
        public bool $onPiece = false,
    ) {
        if ($count < 1 || $count > self::MAX_COUNT) {
            throw InvalidCoat::because(\sprintf('A group holds 1 to %d charges; %d is not.', self::MAX_COUNT, $count));
        }

        if (1 === $count && Arrangement::Usual !== $arrangement) {
            throw InvalidCoat::because('A single charge has no arrangement.');
        }
    }

    /**
     * The arrangement the charges are drawn and written in: two charges set usually stand in pale.
     */
    public function placement(): Arrangement
    {
        return 2 === $this->count && Arrangement::Usual === $this->arrangement ? Arrangement::InPale : $this->arrangement;
    }
}
