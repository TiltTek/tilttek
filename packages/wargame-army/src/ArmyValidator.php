<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Contract\CatalogueProvider;
use Tilttek\WargameArmy\Contract\Constraint;
use Tilttek\WargameArmy\Exception\UnknownCatalogue;

/**
 * Checks an army list against its catalogue, one declared rule at a time: the catalogue and its version, the number
 * of detachments, each detachment's type and slots, each unit's models and options, the points limit, then the
 * catalogue's constraints and the application's. Every violation is returned, none stops the others. The validator
 * keeps nothing between two calls.
 */
final readonly class ArmyValidator
{
    /** @var list<Constraint> */
    private array $constraints;

    /**
     * @param Constraint ...$constraints the application's own constraints, checked after the catalogue's, on every list
     */
    public function __construct(private CatalogueProvider $catalogues, Constraint ...$constraints)
    {
        $this->constraints = array_values($constraints);
    }

    /**
     * @throws UnknownCatalogue when the application does not provide the list's catalogue
     */
    public function validate(ArmyList $list): Validation
    {
        $catalogue = $this->catalogues->find($list->catalogue) ?? throw UnknownCatalogue::named($list->catalogue);

        return $this->validateAgainst($list, $catalogue);
    }

    /**
     * Checks the list against that catalogue, which the list may not name.
     */
    public function validateAgainst(ArmyList $list, Catalogue $catalogue): Validation
    {
        $costing = Costing::of($list, $catalogue);
        $violations = [...$this->catalogue($list, $catalogue), ...$this->detachmentCount($list, $catalogue)];

        foreach ($list->detachments as $index => $detachment) {
            array_push($violations, ...$this->detachment($index, $detachment, $catalogue));
        }

        if ($costing->total > $list->pointsLimit) {
            $violations[] = new Violation(Rule::PointsLimit, \sprintf('The list costs %d points; its limit is %d.', $costing->total, $list->pointsLimit), ['points' => $costing->total, 'limit' => $list->pointsLimit]);
        }

        foreach ([...$catalogue->constraints, ...$this->constraints] as $constraint) {
            array_push($violations, ...$constraint->check($list, $catalogue, $costing));
        }

        return new Validation($list, $catalogue, $costing, $violations);
    }

    /**
     * @return list<Violation>
     */
    private function catalogue(ArmyList $list, Catalogue $catalogue): array
    {
        if ($list->catalogue !== $catalogue->code) {
            return [new Violation(Rule::Catalogue, \sprintf('The list is built with the catalogue "%s"; it is checked against "%s".', $list->catalogue, $catalogue->code), ['catalogue' => $list->catalogue])];
        }

        if ($list->catalogueVersion !== $catalogue->version) {
            return [new Violation(Rule::CatalogueVersion, \sprintf('The list was built with version %s of the catalogue "%s"; the catalogue is at version %s.', $list->catalogueVersion, $catalogue->code, $catalogue->version), ['version' => $list->catalogueVersion])];
        }

        return [];
    }

    /**
     * @return list<Violation>
     */
    private function detachmentCount(ArmyList $list, Catalogue $catalogue): array
    {
        $count = \count($list->detachments);
        $violations = [];

        if ($count < $catalogue->minDetachments) {
            $violations[] = new Violation(Rule::DetachmentCount, \sprintf('The army has %s; the catalogue "%s" takes at least %d.', $this->count($count, 'detachment', 'detachments'), $catalogue->code, $catalogue->minDetachments), ['count' => $count, 'min' => $catalogue->minDetachments]);
        } elseif ($count > $catalogue->maxDetachments) {
            $violations[] = new Violation(Rule::DetachmentCount, \sprintf('The army has %d detachments; the catalogue "%s" takes at most %d.', $count, $catalogue->code, $catalogue->maxDetachments), ['count' => $count, 'max' => $catalogue->maxDetachments]);
        }

        $byType = array_count_values(array_map(static fn (Detachment $detachment): string => $detachment->type, $list->detachments));

        foreach ($byType as $code => $typeCount) {
            $type = $catalogue->detachmentType(Identifiers::key($code));

            if ($type instanceof DetachmentType && null !== $type->max && $typeCount > $type->max) {
                $violations[] = new Violation(Rule::DetachmentLimit, \sprintf('The army has %d detachments "%s"; the catalogue allows at most %d.', $typeCount, $type->name, $type->max), ['type' => $type->code, 'count' => $typeCount, 'max' => $type->max]);
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function detachment(int $index, Detachment $detachment, Catalogue $catalogue): array
    {
        $type = $catalogue->detachmentType($detachment->type);
        $label = \sprintf('Detachment %d "%s"', $index + 1, $detachment->name ?? $type->name ?? $detachment->type);
        $where = ['detachment' => $index + 1];
        $violations = [];

        if (!$type instanceof DetachmentType) {
            $violations[] = new Violation(Rule::UnknownDetachment, \sprintf('%s: the catalogue has no detachment type "%s".', $label, $detachment->type), $where + ['type' => $detachment->type]);
        }

        $filled = [];

        foreach ($detachment->units as $position => $selection) {
            $unit = $catalogue->unit($selection->unit);
            $unitLabel = \sprintf('%s, unit %d "%s"', $label, $position + 1, $unit->name ?? $selection->unit);
            $at = $where + ['unit' => $position + 1];

            if (!$unit instanceof UnitEntry) {
                $violations[] = new Violation(Rule::UnknownUnit, \sprintf('%s: the catalogue has no unit "%s".', $unitLabel, $selection->unit), $at);

                continue;
            }

            array_push($violations, ...$this->unit($unitLabel, $at, $unit, $selection));

            if (!$type instanceof DetachmentType) {
                continue;
            }

            $slot = $type->slotFor($unit);

            if (!$slot instanceof Slot) {
                $violations[] = new Violation(Rule::NotAllowed, \sprintf('%s: the detachment type "%s" takes no unit of its categories.', $unitLabel, $type->name), $at);
            } else {
                $filled[$slot->category] = ($filled[$slot->category] ?? 0) + 1;
            }
        }

        foreach ($type->slots ?? [] as $slot) {
            $count = $filled[$slot->category] ?? 0;
            $values = $where + ['category' => $slot->category, 'count' => $count];

            if ($count < $slot->min) {
                $violations[] = new Violation(Rule::Slot, \sprintf('%s: %s of the category "%s"; the slot takes at least %d.', $label, $this->count($count, 'unit', 'units'), $slot->category, $slot->min), $values + ['min' => $slot->min]);
            } elseif (null !== $slot->max && $count > $slot->max) {
                $violations[] = new Violation(Rule::Slot, \sprintf('%s: %d units of the category "%s"; the slot takes at most %d.', $label, $count, $slot->category, $slot->max), $values + ['max' => $slot->max]);
            }
        }

        return $violations;
    }

    /**
     * @param array<string, int> $at
     *
     * @return list<Violation>
     */
    private function unit(string $label, array $at, UnitEntry $unit, UnitSelection $selection): array
    {
        $violations = [];

        if ($selection->models < $unit->minModels || $selection->models > $unit->maxModels) {
            $range = $unit->minModels === $unit->maxModels ? (string) $unit->minModels : \sprintf('%d to %d', $unit->minModels, $unit->maxModels);
            $violations[] = new Violation(Rule::Models, \sprintf('%s: %s; the unit takes %s.', $label, $this->count($selection->models, 'model', 'models'), $range), $at + ['count' => $selection->models, 'min' => $unit->minModels, 'max' => $unit->maxModels]);
        }

        foreach ($selection->options as $code => $count) {
            $code = (string) $code;
            $option = $unit->option($code);

            if (!$option instanceof Option) {
                $violations[] = new Violation(Rule::UnknownOption, \sprintf('%s: the unit has no option "%s".', $label, $code), $at + ['option' => $code]);
            } elseif ($count > $option->max) {
                $violations[] = new Violation(Rule::OptionLimit, \sprintf('%s: the option "%s" is taken %s; at most %s.', $label, $option->name, $this->count($count, 'time', 'times'), $this->count($option->max, 'time', 'times')), $at + ['option' => $code, 'count' => $count, 'max' => $option->max]);
            }
        }

        foreach ($unit->optionGroups as $group) {
            $taken = 0;

            foreach (array_keys($group->options) as $code) {
                $taken += $selection->options[$code] ?? 0;
            }

            $values = $at + ['group' => $group->code, 'count' => $taken];

            if ($taken < $group->min) {
                $violations[] = new Violation(Rule::OptionGroup, \sprintf('%s: %s in "%s"; the group takes at least %d.', $label, $this->count($taken, 'selection', 'selections'), $group->name, $group->min), $values + ['min' => $group->min]);
            } elseif ($taken > $group->max) {
                $violations[] = new Violation(Rule::OptionGroup, \sprintf('%s: %d selections in "%s"; the group takes at most %d.', $label, $taken, $group->name, $group->max), $values + ['max' => $group->max]);
            }
        }

        return $violations;
    }

    /**
     * A number with its noun: "1 unit", "0 units", "2 units".
     */
    private function count(int $count, string $one, string $many): string
    {
        return \sprintf('%d %s', $count, 1 === $count ? $one : $many);
    }
}
