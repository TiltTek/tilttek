<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;
use Tilttek\Grid\Ratio;

/**
 * The cube coordinates of a hexagon: three axes q, r and s whose sum is zero, in which a rotation or a reflection is a
 * swap of axes.
 */
final readonly class Cube
{
    /**
     * @throws InvalidGrid when the three coordinates do not add up to zero
     * @throws OutOfBounds when a coordinate is out of bounds
     */
    public function __construct(public int $q, public int $r, public int $s)
    {
        Limits::coordinate($q, 'q');
        Limits::coordinate($r, 'r');
        Limits::coordinate($s, 's');
        if (0 !== $q + $r + $s) {
            throw InvalidGrid::because(\sprintf('Cube coordinates add up to zero; %d + %d + %d does not.', $q, $r, $s));
        }
    }

    public function toAxial(): Axial
    {
        return new Axial($this->q, $this->r);
    }

    public function equals(self $other): bool
    {
        return $this->q === $other->q && $this->r === $other->r;
    }

    /**
     * The hexagon nearest a fractional position, given as three fractions of a common positive denominator whose
     * numerators add up to zero: the nearest integer on each axis, the axis that moved most then fixed by the others.
     *
     * @throws OutOfBounds when the hexagon is out of bounds
     */
    public static function nearest(int $q, int $r, int $s, int $denominator): self
    {
        [$q, $r, $s] = self::round($q, $r, $s, $denominator);

        return new self($q, $r, $s);
    }

    /**
     * The coordinates {@see self::nearest()} returns, without checking their bounds: the inner step of a line.
     *
     * @return array{int, int, int}
     */
    public static function round(int $q, int $r, int $s, int $denominator): array
    {
        $roundedQ = Limits::roundHalfUp($q, $denominator);
        $roundedR = Limits::roundHalfUp($r, $denominator);
        $roundedS = Limits::roundHalfUp($s, $denominator);
        $dq = abs($roundedQ * $denominator - $q);
        $dr = abs($roundedR * $denominator - $r);
        $ds = abs($roundedS * $denominator - $s);

        if ($dq > $dr && $dq > $ds) {
            $roundedQ = -$roundedR - $roundedS;
        } elseif ($dr > $ds) {
            $roundedR = -$roundedQ - $roundedS;
        } else {
            $roundedS = -$roundedQ - $roundedR;
        }

        return [$roundedQ, $roundedR, $roundedS];
    }

    /**
     * The hexagon nearest a fractional position given by its exact q and r.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public static function nearestTo(Ratio $q, Ratio $r): self
    {
        $s = $q->plus($r)->negated();
        // The denominator of s divides the least common multiple of those of q and r, which serves all three.
        $denominator = Ratio::of($q->denominator)->times(Ratio::of(Ratio::of($q->denominator, $r->denominator)->denominator))->numerator;
        $scale = Ratio::of($denominator);

        return self::nearest(
            $q->times($scale)->numerator,
            $r->times($scale)->numerator,
            $s->times($scale)->numerator,
            $denominator,
        );
    }
}
