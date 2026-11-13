<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Generator;

use Random\Randomizer;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\Tincture;
use Tilttek\Heraldry\TinctureKind;

/**
 * Draws a coat of arms from a seed: the same seed gives the same arms, and every coat it draws keeps the rule of
 * tincture.
 *
 * A field is plain three times in five, divided otherwise between a metal and a colour, or a fur and a colour; an
 * ordinary comes a little more than half the time; charges come on most coats, on the ordinary or beside it; a mark of
 * difference comes on one coat in ten.
 */
final readonly class CoatGenerator
{
    /**
     * @param bool $furs whether a field may be of ermine or vair
     */
    public function __construct(
        private bool $furs = true,
    ) {
    }

    public function generate(Seed $seed): Coat
    {
        $random = $seed->randomizer();
        $field = $this->field($random);
        $piece = $this->chance($random, 55) ? $this->piece($random, $field) : null;
        $charges = $this->charges($random, $field, $piece);
        $brisure = $this->chance($random, 10) ? $this->brisure($random, $field, $piece) : null;

        return new Coat($field, $piece, $charges, $brisure);
    }

    private function field(Randomizer $random): Field
    {
        $metals = Tincture::ofKind(TinctureKind::Metal);
        $colours = Tincture::ofKind(TinctureKind::Colour);
        $furs = $this->furs ? Tincture::ofKind(TinctureKind::Fur) : [];

        if ($this->chance($random, 60)) {
            return Field::plain($this->pick($random, [...$metals, ...$metals, ...$colours, ...$colours, ...$furs]));
        }

        $light = $this->pick($random, [...$metals, ...$metals, ...$metals, ...$furs]);
        $colour = $this->pick($random, $colours);
        [$first, $second] = $this->chance($random, 50) ? [$light, $colour] : [$colour, $light];

        return Field::divided($this->pick($random, Partition::cases()), $first, $second);
    }

    private function piece(Randomizer $random, Field $field): Piece
    {
        return new Piece($this->pick($random, Ordinary::cases()), $this->tinctureOn($random, $field, null));
    }

    private function charges(Randomizer $random, Field $field, ?Piece $piece): ?ChargeGroup
    {
        if (!$piece instanceof Piece) {
            if (!$this->chance($random, 85)) {
                return null;
            }

            $count = $random->getInt(1, ChargeGroup::MAX_COUNT);
            $arrangement = 1 === $count || $this->chance($random, 70)
                ? Arrangement::Usual
                : $this->pick($random, [Arrangement::InFess, Arrangement::InPale]);

            return new ChargeGroup($this->pick($random, Charge::cases()), $this->tinctureOn($random, $field, null), $count, $arrangement);
        }

        $on = $piece->ordinary->chargesOn();
        $beside = $piece->ordinary->chargesBeside();

        if (!$this->chance($random, 50)) {
            return null;
        }

        $onPiece = match ([] === $on ? 'beside' : ([] === $beside ? 'on' : 'either')) {
            'beside' => false,
            'on' => true,
            'either' => $this->chance($random, 50),
        };

        return new ChargeGroup(
            $this->pick($random, Charge::cases()),
            $onPiece ? $this->tinctureOn($random, null, $piece->tincture) : $this->tinctureOn($random, $field, $piece->tincture),
            $this->pick($random, $onPiece ? $on : $beside),
            onPiece: $onPiece,
        );
    }

    private function brisure(Randomizer $random, Field $field, ?Piece $piece): Brisure
    {
        return new Brisure($this->pick($random, BrisureMark::cases()), $this->tinctureOn($random, $field, $piece?->tincture));
    }

    /**
     * A tincture that may lie on the ground: on a plain field or on the ordinary, one the rule of tincture allows; on a
     * divided field, any tincture but those of the field. Never the tincture to avoid, so that what lies side by side
     * stays apart.
     */
    private function tinctureOn(Randomizer $random, ?Field $field, ?Tincture $avoid): Tincture
    {
        $candidates = array_filter(
            [...Tincture::ofKind(TinctureKind::Metal), ...Tincture::ofKind(TinctureKind::Colour)],
            static fn (Tincture $tincture): bool => $tincture !== $avoid && match (true) {
                !$field instanceof Field => $tincture->contrastsWith($avoid ?? $tincture),
                $field->isPlain() => $tincture->contrastsWith($field->tincture()),
                default => !$field->has($tincture),
            },
        );

        return $this->pick($random, array_values($candidates));
    }

    private function chance(Randomizer $random, int $percent): bool
    {
        return $random->getInt(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param non-empty-list<T>|list<T> $choices
     *
     * @return T
     */
    private function pick(Randomizer $random, array $choices): mixed
    {
        \assert([] !== $choices);

        return $choices[$random->getInt(0, \count($choices) - 1)];
    }
}
