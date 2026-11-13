<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Svg;

use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\ShieldShape;

/**
 * The shapes the arms are drawn with, all drawn for this package on a shield of 200 by 240 units: the outlines, the
 * partitions, the ordinaries, the charges in a box of 100 units centred on the origin, the marks of difference, and
 * where each charge goes.
 *
 * @internal
 */
final class Shapes
{
    public const string HEATER = 'M0,0 H200 V100 C200,170 150,215 100,240 C50,215 0,170 0,100 Z';

    /** The region of the field the charges take when no ordinary places them: x, y, width, height. */
    public const array FIELD = [0, 0, 200, 210];

    /** The region below a chief. */
    public const array BELOW_CHIEF = [0, 66, 200, 154];

    /** The region within a bordure. */
    public const array WITHIN_BORDURE = [22, 22, 156, 176];

    private function __construct()
    {
    }

    public static function shield(ShieldShape $shape): string
    {
        return match ($shape) {
            ShieldShape::Heater => self::HEATER,
            ShieldShape::French => 'M0,0 H200 V205 Q200,220 185,220 H125 Q108,220 100,240 Q92,220 75,220 H15 Q0,220 0,205 Z',
            ShieldShape::Iberian => 'M0,0 H200 V140 A100,100 0 0 1 0,140 Z',
            ShieldShape::Lozenge => 'M100,0 L200,120 L100,240 L0,120 Z',
        };
    }

    /** The part of a divided field in its second tincture. */
    public static function partition(Partition $partition): string
    {
        return match ($partition) {
            Partition::PerPale => 'M100,0 H200 V240 H100 Z',
            Partition::PerFess => 'M0,120 H200 V240 H0 Z',
            Partition::PerBend => 'M0,0 L200,240 H0 Z',
            Partition::PerBendSinister => 'M200,0 V240 H0 Z',
            Partition::Quarterly => 'M100,0 H200 V120 H100 Z M0,120 H100 V240 H0 Z',
            Partition::PerSaltire => 'M0,0 L100,120 L0,240 Z M200,0 L100,120 L200,240 Z',
        };
    }

    /**
     * The paths of an ordinary, each filled on its own; a bordure is drawn as the stroke of the shield instead.
     *
     * @return list<string>
     */
    public static function ordinary(Ordinary $ordinary): array
    {
        return match ($ordinary) {
            Ordinary::Chief => ['M0,0 H200 V66 H0 Z'],
            Ordinary::Fess => ['M0,85 H200 V145 H0 Z'],
            Ordinary::Pale => ['M70,0 H130 V240 H70 Z'],
            Ordinary::Bend => ['M-16.95,-67.21 L263.05,268.8 L216.95,307.21 L-63.05,-28.8 Z'],
            Ordinary::BendSinister => ['M216.95,-67.21 L-63.05,268.8 L-16.95,307.21 L263.05,-28.8 Z'],
            Ordinary::Chevron => ['M0,190 L100,70 L200,190 V234 L100,114 L0,234 Z'],
            Ordinary::Cross => ['M75,0 H125 V240 H75 Z', 'M0,90 H200 V140 H0 Z'],
            Ordinary::Saltire => ['M-23.1,-62.08 L256.9,273.92 L223.1,302.08 L-56.9,-33.92 Z', 'M223.1,-62.08 L-56.9,273.92 L-23.1,302.08 L256.9,-33.92 Z'],
            Ordinary::Bordure => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function brisure(BrisureMark $mark): array
    {
        return match ($mark) {
            BrisureMark::Label => ['M30,22 H170 V32 H30 Z', 'M50,31 H66 V58 H50 Z', 'M92,31 H108 V58 H92 Z', 'M134,31 H150 V58 H134 Z'],
            BrisureMark::Bendlet => ['M-33.09,-53.76 L246.91,282.24 L233.09,293.76 L-46.91,-42.24 Z'],
        };
    }

    /**
     * The paths of a charge in its box of 100 units, and whether they are filled even-odd, leaving a hole.
     *
     * @return array{list<string>, bool}
     */
    public static function charge(Charge $charge): array
    {
        return match ($charge) {
            Charge::Mullet => [[self::star()], false],
            Charge::Crescent => [['M-44,-27 A45,45 0 1 0 44,-27 A50,50 0 0 1 -44,-27 Z'], false],
            Charge::Roundel => [[self::circle(0, 0, 45)], false],
            Charge::Annulet => [[self::circle(0, 0, 45).' '.self::circle(0, 0, 30)], true],
            Charge::Lozenge => [['M0,-50 L34,0 L0,50 L-34,0 Z'], false],
            Charge::Mascle => [['M0,-50 L34,0 L0,50 L-34,0 Z M0,-28 L19,0 L0,28 L-19,0 Z'], true],
            Charge::Billet => [['M-24,-45 H24 V45 H-24 Z'], false],
            Charge::FleurDeLis => [[
                'M0,-50 C16,-32 15,-12 6,2 H-6 C-15,-12 -16,-32 0,-50 Z',
                'M6,2 C14,-10 28,-22 40,-18 C50,-14 48,0 38,2 C42,-6 36,-12 30,-8 C22,-4 16,0 14,6 Z',
                'M-6,2 C-14,-10 -28,-22 -40,-18 C-50,-14 -48,0 -38,2 C-42,-6 -36,-12 -30,-8 C-22,-4 -16,0 -14,6 Z',
                'M-8,14 C-20,16 -30,26 -32,40 C-24,32 -16,26 -8,24 Z',
                'M8,14 C20,16 30,26 32,40 C24,32 16,26 8,24 Z',
                'M-6,14 H6 L10,42 L0,34 L-10,42 Z',
                'M-24,4 H24 V14 H-24 Z',
            ], false],
        };
    }

    /**
     * Where the charges go in a region of the field: x and y of each centre, and the size of their box.
     *
     * @param array{int, int, int, int} $region x, y, width, height
     *
     * @return list<array{float, float, float}>
     */
    public static function inRegion(array $region, int $count, Arrangement $placement): array
    {
        [$x, $y, $width, $height] = $region;
        $scale = min($width, $height);
        $spots = match (true) {
            1 === $count => [[0.5, 0.5, 0.4]],
            Arrangement::InFess === $placement && 2 === $count => [[0.29, 0.5, 0.28], [0.71, 0.5, 0.28]],
            Arrangement::InFess === $placement => [[0.2, 0.5, 0.2], [0.5, 0.5, 0.2], [0.8, 0.5, 0.2]],
            2 === $count => [[0.5, 0.3, 0.28], [0.5, 0.72, 0.28]],
            Arrangement::InPale === $placement => [[0.5, 0.21, 0.2], [0.5, 0.5, 0.2], [0.5, 0.79, 0.2]],
            default => [[0.28, 0.33, 0.26], [0.72, 0.33, 0.26], [0.5, 0.72, 0.26]],
        };

        return array_map(static fn (array $spot): array => [$x + $spot[0] * $width, $y + $spot[1] * $height, $spot[2] * $scale], $spots);
    }

    /**
     * Where the charges go on an ordinary.
     *
     * @return list<array{float, float, float}>
     */
    public static function onOrdinary(Ordinary $ordinary, int $count): array
    {
        $row = [1 => [100], 2 => [65, 135], 3 => [40, 100, 160]][$count] ?? [];
        $column = [1 => [115], 2 => [70, 160], 3 => [45, 115, 185]][$count] ?? [];
        $along = [1 => [0.43], 2 => [0.3, 0.56], 3 => [0.2, 0.43, 0.66]][$count] ?? [];

        $spots = match ($ordinary) {
            Ordinary::Chief => array_map(static fn (int $x): array => [$x, 33, 46], $row),
            Ordinary::Fess => array_map(static fn (int $x): array => [$x, 115, 46], $row),
            Ordinary::Pale => array_map(static fn (int $y): array => [100, $y, 46], $column),
            Ordinary::Bend => array_map(static fn (float $t): array => [200 * $t, 240 * $t, 38], $along),
            Ordinary::BendSinister => array_map(static fn (float $t): array => [200 - 200 * $t, 240 * $t, 38], $along),
            Ordinary::Chevron => 1 === $count ? [[100, 94, 32]] : [[42, 163, 32], [100, 94, 32], [158, 163, 32]],
            Ordinary::Cross => [[100, 115, 42]],
            Ordinary::Saltire => [[100, 120, 38]],
            Ordinary::Bordure => [],
        };

        return array_values(array_map(static fn (array $spot): array => [(float) $spot[0], (float) $spot[1], (float) $spot[2]], $spots));
    }

    /**
     * Where the charges go around an ordinary that surrounds them.
     *
     * @return list<array{float, float, float}>
     */
    public static function aroundOrdinary(Ordinary $ordinary, int $count): array
    {
        $spots = match ($ordinary) {
            Ordinary::Fess => 2 === $count ? [[100, 44, 46], [100, 186, 46]] : [[55, 44, 44], [145, 44, 44], [100, 186, 44]],
            Ordinary::Pale => [[36, 110, 44], [164, 110, 44]],
            Ordinary::Bend => [[150, 62, 46], [52, 172, 46]],
            Ordinary::BendSinister => [[50, 62, 46], [148, 172, 46]],
            Ordinary::Chevron => [[46, 52, 40], [154, 52, 40], [100, 178, 40]],
            default => [],
        };

        return array_map(static fn (array $spot): array => [(float) $spot[0], (float) $spot[1], (float) $spot[2]], $spots);
    }

    /** A circle as a path: two half arcs. */
    public static function circle(float $x, float $y, float $radius): string
    {
        return \sprintf(
            'M%s,%s a%s,%s 0 1 0 %s,0 a%s,%s 0 1 0 %s,0 Z',
            self::number($x - $radius),
            self::number($y),
            self::number($radius),
            self::number($radius),
            self::number(2 * $radius),
            self::number($radius),
            self::number($radius),
            self::number(-2 * $radius),
        );
    }

    /** A number as SVG writes it: at most two decimals, no trailing zero. */
    public static function number(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }

    /** A star of five points, the first upward, its inner points at 0.382 of its radius of 50. */
    private static function star(): string
    {
        $points = [];

        for ($i = 0; $i < 10; ++$i) {
            $radius = 0 === $i % 2 ? 50 : 19.1;
            $angle = -\M_PI / 2 + $i * \M_PI / 5;
            $points[] = self::number($radius * cos($angle)).','.self::number($radius * sin($angle));
        }

        return 'M'.implode(' L', $points).' Z';
    }
}
