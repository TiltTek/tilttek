<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Constraint;

use Override;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArrayInput;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Contract\DeclaredConstraint;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Fault;
use Tilttek\WargameArmy\Identifiers;
use Tilttek\WargameArmy\Rule;
use Tilttek\WargameArmy\Violation;

/**
 * The fewest and the most units selected, in the army or in each detachment — "one warlord", "at most three
 * monsters", "at least one leader per detachment".
 */
final readonly class UnitCount implements DeclaredConstraint
{
    public int $min;

    /**
     * The most units; null for no limit.
     */
    public ?int $max;

    /**
     * @throws InvalidCatalogue when a bound is refused, or both are absent
     */
    public function __construct(public Selector $selector, public Scope $scope = Scope::Army, int $min = 0, ?int $max = null)
    {
        $limit = ArmyList::MAX_DETACHMENTS * Detachment::MAX_UNITS;
        $this->min = Identifiers::bounded($min, 0, $limit, 'the fewest units of a count');
        $this->max = null === $max ? null : Identifiers::bounded($max, $min, $limit, 'the most units of a count');

        if (0 === $min && null === $max) {
            throw InvalidCatalogue::because('A count has a "min" above 0 or a "max".');
        }
    }

    /**
     * @param mixed $data the constraint, whose `type` names this class
     *
     * @throws InvalidCatalogue when the array does not read as a count
     */
    public static function fromArray(mixed $data): self
    {
        $input = new ArrayInput(Fault::Catalogue);
        $object = $input->object($data, 'count', ['type'], ['unit', 'category', 'scope', 'min', 'max']);
        $scope = Scope::tryFrom($input->string($object + ['scope' => 'army'], 'scope', 'count'));

        if (null === $scope) {
            throw InvalidCatalogue::because('The "scope" of a count is "army" or "detachment".');
        }

        return new self(Selector::fromArray($object), $scope, $input->int($object, 'min', 'count', 0), $input->nullableInt($object, 'max', 'count'));
    }

    #[Override]
    public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
    {
        if (Scope::Army === $this->scope) {
            $violation = $this->compare($costing->count($this->selector, $catalogue), 'the army has', 'in the army', $catalogue);

            return $violation instanceof Violation ? [$violation] : [];
        }

        $violations = [];

        foreach (array_keys($list->detachments) as $index) {
            $violation = $this->compare($costing->count($this->selector, $catalogue, $index), \sprintf('detachment %d has', $index + 1), 'in each detachment', $catalogue, $index);

            if ($violation instanceof Violation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return array{type: string, unit?: string, category?: string, scope: string, min: int, max: ?int}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'count'] + $this->selector->toArray() + ['scope' => $this->scope->value, 'min' => $this->min, 'max' => $this->max];
    }

    private function compare(int $count, string $has, string $where, Catalogue $catalogue, ?int $detachment = null): ?Violation
    {
        $label = $this->selector->label($catalogue);
        $values = $this->selector->toArray() + ['count' => $count];

        if (null !== $detachment) {
            $values['detachment'] = $detachment + 1;
        }

        if ($count < $this->min) {
            return new Violation(Rule::Constraint, \sprintf('At least %d %s %s; %s %d.', $this->min, $label, $where, $has, $count), $values + ['min' => $this->min]);
        }

        if (null !== $this->max && $count > $this->max) {
            return new Violation(Rule::Constraint, \sprintf('At most %d %s %s; %s %d.', $this->max, $label, $where, $has, $count), $values + ['max' => $this->max]);
        }

        return null;
    }
}
