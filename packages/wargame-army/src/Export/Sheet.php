<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Export;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Validation;

/**
 * The lines of an army list as people read it, before they are written as text or HTML: plain strings, never
 * escaped.
 *
 * @internal
 */
final readonly class Sheet
{
    /**
     * @param list<array{heading: string, units: list<array{label: string, points: string, options: list<string>}>}> $detachments
     * @param list<string>                                                                                           $violations
     */
    private function __construct(
        public string $title,
        public string $subtitle,
        public array $detachments,
        public array $violations,
    ) {
    }

    public static function of(ArmyList $list, Catalogue $catalogue, ?Validation $validation = null): self
    {
        $costing = $validation->costing ?? Costing::of($list, $catalogue);
        $detachments = [];

        foreach ($list->detachments as $index => $detachment) {
            $type = $catalogue->detachmentType($detachment->type)->name ?? $detachment->type;
            $heading = null === $detachment->name ? $type : \sprintf('%s (%s)', $detachment->name, $type);
            $units = [];

            foreach ($detachment->units as $position => $selection) {
                $unit = $catalogue->unit($selection->unit);
                $options = [];

                foreach ($selection->options as $code => $count) {
                    $options[] = \sprintf('%s ×%d', $unit?->option((string) $code)->name ?? $code, $count);
                }

                $units[] = [
                    'label' => \sprintf('%s (%d %s)', $unit->name ?? $selection->unit, $selection->models, 1 === $selection->models ? 'model' : 'models'),
                    'points' => self::points($costing->unit($index, $position)),
                    'options' => $options,
                ];
            }

            $detachments[] = ['heading' => \sprintf('Detachment %d: %s — %s', $index + 1, $heading, self::points($costing->detachment($index))), 'units' => $units];
        }

        return new self(
            $list->name,
            \sprintf('%s %s — %d / %d points', $catalogue->name, $list->catalogueVersion, $costing->total, $list->pointsLimit),
            $detachments,
            $validation?->messages() ?? [],
        );
    }

    private static function points(int $points): string
    {
        return \sprintf('%d %s', $points, 1 === $points ? 'point' : 'points');
    }
}
