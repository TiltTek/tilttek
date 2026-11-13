<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

use Tilttek\Heraldry\Exception\InvalidCoat;

/**
 * A coat of arms as a structure: its field, at most one ordinary, at most one group of charges, and at most one mark
 * of difference. The blazon and the drawing are both written from it.
 *
 * The model holds coats that break the rule of tincture — such arms exist — and `TinctureRule` tells them apart.
 */
final readonly class Coat
{
    /**
     * @throws InvalidCoat when the charges lie on a missing ordinary, or in a number or arrangement it does not take
     */
    public function __construct(
        public Field $field,
        public ?Piece $piece = null,
        public ?ChargeGroup $charges = null,
        public ?Brisure $brisure = null,
    ) {
        if ($charges instanceof ChargeGroup) {
            $this->check($piece, $charges);
        }
    }

    /**
     * @throws InvalidCoat
     */
    private function check(?Piece $piece, ChargeGroup $charges): void
    {
        if (!$piece instanceof Piece) {
            if ($charges->onPiece) {
                throw InvalidCoat::because('Charges on an ordinary need an ordinary.');
            }

            return;
        }

        $ordinary = $piece->ordinary;
        $counts = $charges->onPiece ? $ordinary->chargesOn() : $ordinary->chargesBeside();

        if (!\in_array($charges->count, $counts, true)) {
            throw InvalidCoat::because(\sprintf('A %s takes %s %s; %d is not.', str_replace('_', ' ', $ordinary->value), [] === $counts ? 'no charges' : implode(' or ', $counts).' charges', $charges->onPiece ? 'on itself' : 'beside it', $charges->count));
        }

        if (($charges->onPiece || $ordinary->surroundsCharges()) && Arrangement::Usual !== $charges->arrangement) {
            throw InvalidCoat::because('Charges on or around an ordinary follow it: they take no arrangement of their own.');
        }
    }
}
