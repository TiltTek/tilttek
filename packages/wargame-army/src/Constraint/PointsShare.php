<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Constraint;

use Override;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArrayInput;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Contract\DeclaredConstraint;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Fault;
use Tilttek\WargameArmy\Identifiers;
use Tilttek\WargameArmy\Rule;
use Tilttek\WargameArmy\Violation;

/**
 * The share of the list's points limit the units selected may cost — "heroes, at most 25 %", "troops, at least
 * 25 %" —, in whole percents, compared exactly.
 */
final readonly class PointsShare implements DeclaredConstraint
{
    public int $minPercent;

    public int $maxPercent;

    /**
     * @throws InvalidCatalogue when a percent is refused, or the share allows everything
     */
    public function __construct(public Selector $selector, int $minPercent = 0, int $maxPercent = 100)
    {
        $this->minPercent = Identifiers::bounded($minPercent, 0, 100, 'the least share of a points share');
        $this->maxPercent = Identifiers::bounded($maxPercent, $minPercent, 100, 'the most share of a points share');

        if (0 === $minPercent && 100 === $maxPercent) {
            throw InvalidCatalogue::because('A points share has a "min_percent" above 0 or a "max_percent" below 100.');
        }
    }

    /**
     * @param mixed $data the constraint, whose `type` names this class
     *
     * @throws InvalidCatalogue when the array does not read as a points share
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'points share', ['type'], ['unit', 'category', 'min_percent', 'max_percent']);

        return new self(Selector::fromArray($object), $input->int($object, 'min_percent', 'points share', 0), $input->int($object, 'max_percent', 'points share', 100));
    }

    #[Override]
    public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
    {
        $spent = $costing->points($this->selector, $catalogue);

        $limit = $list->pointsLimit;
        $label = ucfirst($this->selector->label($catalogue));
        $values = $this->selector->toArray() + ['points' => $spent, 'limit' => $limit];

        if ($spent * 100 > $limit * $this->maxPercent) {
            return [new Violation(Rule::Constraint, \sprintf('%s cost at most %d %% of the points limit, %d points; the list spends %d.', $label, $this->maxPercent, intdiv($limit * $this->maxPercent, 100), $spent), $values + ['max_percent' => $this->maxPercent])];
        }

        if ($spent * 100 < $limit * $this->minPercent) {
            return [new Violation(Rule::Constraint, \sprintf('%s cost at least %d %% of the points limit, %d points; the list spends %d.', $label, $this->minPercent, intdiv($limit * $this->minPercent + 99, 100), $spent), $values + ['min_percent' => $this->minPercent])];
        }

        return [];
    }

    /**
     * @return array{type: string, unit?: string, category?: string, min_percent: int, max_percent: int}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'points-share'] + $this->selector->toArray() + ['min_percent' => $this->minPercent, 'max_percent' => $this->maxPercent];
    }
}
