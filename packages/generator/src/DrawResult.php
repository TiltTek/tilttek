<?php

declare(strict_types=1);

namespace Tilttek\Generator;

/**
 * The results of a draw — texts, structured results or maps —, whether the draw found fewer unique results than it
 * asked for, and the state a generator with a memory is left in, for its next draw.
 */
final readonly class DrawResult
{
    /**
     * @param list<mixed>                $results
     * @param array<string, string>|null $state   the state after the last result, or null for a generator without one
     */
    public function __construct(public string $generator, public array $results, public int $requested, public ?array $state = null)
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
     * @return array{generator: string, results: list<mixed>, requested: int, short: bool, state?: array<string, string>}
     */
    public function toArray(): array
    {
        $array = ['generator' => $this->generator, 'results' => $this->results, 'requested' => $this->requested, 'short' => $this->isShort()];

        if (null !== $this->state) {
            $array['state'] = $this->state;
        }

        return $array;
    }
}
