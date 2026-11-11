<?php

declare(strict_types=1);

namespace Tilttek\Dice\Fairness;

use Tilttek\Dice\Result\PoolResult;

/**
 * A roll anyone can check: the commitment published before it, the seed revealed after, the seed the player chose,
 * the number of the roll, and the result with the rounding of its divisions.
 */
final readonly class VerifiableRoll
{
    public function __construct(
        public Commitment $commitment,
        public Seed $seed,
        public string $clientSeed,
        public int $nonce,
        public PoolResult $result,
    ) {
    }

    /**
     * What the roller publishes once the seed is revealed: enough for anyone to verify the roll.
     *
     * @return array{commitment: string, seed: string, client_seed: string, nonce: int, expression: string, rounding: string, result: list<array{expression: string, total: ?int, output: int|string|list<int|string>, merged_into: ?int, detail: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'commitment' => $this->commitment->hex,
            'seed' => $this->seed->hex(),
            'client_seed' => $this->clientSeed,
            'nonce' => $this->nonce,
            'expression' => $this->result->pool->canonical(),
            'rounding' => $this->result->rounding->name,
            'result' => $this->result->toArray(),
        ];
    }
}
