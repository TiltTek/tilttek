<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The rule of tincture: metal is never laid on metal nor colour on colour. A fur goes on either; nothing is laid on its
 * own tincture.
 *
 * The rule binds what lies on a plain field and what lies on the ordinary. Whatever lies on a divided field touches both
 * of its tinctures and is not bound by it, nor is a mark of difference, which lies over everything.
 */
final readonly class TinctureRule
{
    /**
     * @return list<Violation>
     */
    public function violations(Coat $coat): array
    {
        $violations = [];
        $field = $coat->field;
        $piece = $coat->piece;
        $charges = $coat->charges;

        if ($piece instanceof Piece && $field->isPlain() && !$piece->tincture->contrastsWith($field->tincture())) {
            $violations[] = new Violation('piece', $piece->tincture, $field->tincture());
        }

        if ($charges instanceof ChargeGroup) {
            $ground = $charges->onPiece && $piece instanceof Piece ? $piece->tincture : ($field->isPlain() ? $field->tincture() : null);

            if ($ground instanceof Tincture && !$charges->tincture->contrastsWith($ground)) {
                $violations[] = new Violation('charges', $charges->tincture, $ground);
            }
        }

        return $violations;
    }

    public function respects(Coat $coat): bool
    {
        return [] === $this->violations($coat);
    }
}
