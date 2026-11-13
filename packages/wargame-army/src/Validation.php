<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

/**
 * What the validation of an army list found: every violation, from the list's own to its detachments', its units' and
 * the constraints', with the points the list costs.
 */
final readonly class Validation
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public ArmyList $list,
        public Catalogue $catalogue,
        public Costing $costing,
        public array $violations,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->violations;
    }

    /**
     * The messages of the violations, in their order.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (Violation $violation): string => $violation->message, $this->violations);
    }

    /**
     * The violations of one rule.
     *
     * @return list<Violation>
     */
    public function ofRule(Rule $rule): array
    {
        return array_values(array_filter($this->violations, static fn (Violation $violation): bool => $rule === $violation->rule));
    }
}
