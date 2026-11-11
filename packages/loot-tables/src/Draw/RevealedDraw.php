<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

use Tilttek\Dice\Fairness\VerifiableRoll;

/**
 * A draw revealed: its result, and the roll of `tilttek/dice` it came from, seed included.
 */
final readonly class RevealedDraw
{
    public function __construct(
        public LootResult $result,
        public VerifiableRoll $roll,
    ) {
    }

    /**
     * What is published once the draw is revealed: the box, the faces, and the verifiable roll of `tilttek/dice` —
     * enough for `LootDrawer::verify()`.
     *
     * @return array{box: string, faces: non-empty-list<int>, commitment: string, seed: string, client_seed: string, nonce: int, expression: string, rounding: string, result: list<array{expression: string, total: ?int, output: int|string|list<int|string>, merged_into: ?int, detail: array<string, mixed>}>}
     */
    public function proof(): array
    {
        return ['box' => $this->result->box->key, 'faces' => $this->result->faces(), ...$this->roll->toArray()];
    }
}
