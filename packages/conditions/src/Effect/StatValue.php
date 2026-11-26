<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Effect;

/**
 * A statistic once the conditions are counted: its base, its value, and the modifiers that made the difference, to
 * show the player why.
 */
final readonly class StatValue
{
    /**
     * @param list<AppliedModifier> $contributions in the order they ran
     */
    public function __construct(
        public string $stat,
        public int $base,
        public int $value,
        public array $contributions,
    ) {
    }

    /**
     * @return array{stat: string, base: int, value: int, contributions: list<array{condition: string, instance: ?string, operation: string, amount: int}>}
     */
    public function toArray(): array
    {
        return [
            'stat' => $this->stat,
            'base' => $this->base,
            'value' => $this->value,
            'contributions' => array_map(static fn (AppliedModifier $one): array => [
                'condition' => $one->condition,
                'instance' => $one->instance,
                'operation' => $one->modifier->operation->value,
                'amount' => $one->amount,
            ], $this->contributions),
        ];
    }
}
