<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Tilttek\Generator\Definition\Attribution;

/**
 * The results of a draw — texts, structured results or maps —, whether the draw found fewer unique results than it
 * asked for, the state a generator with a memory is left in, for its next draw, and the attributions of the lists,
 * entries and generators the results used, to show with them.
 */
final readonly class DrawResult
{
    /**
     * @param list<mixed>                $results
     * @param array<string, string>|null $state        the state after the last result, or null for a generator without one
     * @param list<Attribution>          $attributions in the order the draw first used them, each once
     */
    public function __construct(public string $generator, public array $results, public int $requested, public ?array $state = null, public array $attributions = [])
    {
    }

    /**
     * Whether the lists did not have enough to give every result asked for.
     */
    public function isShort(): bool
    {
        return \count($this->results) < $this->requested;
    }

    public function first(): mixed
    {
        return $this->results[0] ?? null;
    }

    /**
     * @return array{generator: string, results: list<mixed>, requested: int, short: bool, state?: array<string, string>, attributions?: list<array{source?: string, licence?: string, attribution?: string}>}
     */
    public function toArray(): array
    {
        $array = ['generator' => $this->generator, 'results' => $this->results, 'requested' => $this->requested, 'short' => $this->isShort()];

        if (null !== $this->state) {
            $array['state'] = $this->state;
        }

        if ([] !== $this->attributions) {
            $array['attributions'] = array_map(static fn (Attribution $attribution): array => $attribution->toArray(), $this->attributions);
        }

        return $array;
    }
}
