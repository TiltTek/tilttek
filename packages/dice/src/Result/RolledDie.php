<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Tilttek\Dice\Custom\Face;

/**
 * One die as it fell and what became of it: its value — the face's value for a custom die, 0 when its faces carry
 * none —, the face of a custom die, why it was rolled, whether it counts, whether a reroll replaced it, whether it
 * exploded, whether it is a success or a failure when the expression counts them, its critical mark, its look token,
 * and the rolls it is made of: one, or several for a die that compounds its explosions.
 */
final readonly class RolledDie
{
    /**
     * @var non-empty-list<int>
     */
    public array $rolls;

    /**
     * @param ?non-empty-list<int> $rolls the rolls the die is made of, once each penetration is taken away; the value
     *                                    alone when null
     */
    public function __construct(
        public int $value,
        public DieOrigin $origin = DieOrigin::Initial,
        public bool $kept = true,
        public bool $rerolled = false,
        public bool $exploded = false,
        public ?bool $success = null,
        public ?Face $face = null,
        public bool $failure = false,
        public ?Mark $critical = null,
        public ?string $look = null,
        ?array $rolls = null,
    ) {
        $this->rolls = $rolls ?? [$value];
    }

    /**
     * The die as it fell, before its floor, its ceiling or its transformation: the sum of its rolls.
     */
    public function natural(): int
    {
        return array_sum($this->rolls);
    }

    /**
     * Whether the die is one of those that count: kept, and not replaced by a reroll.
     */
    public function counts(): bool
    {
        return $this->kept && !$this->rerolled;
    }

    /**
     * The same die, left out by a filter or a selection.
     */
    public function dropped(): self
    {
        return clone ($this, ['kept' => false]);
    }

    /**
     * The same die, exploded with every other die of its set, all the same.
     */
    public function exploding(): self
    {
        return clone ($this, ['exploded' => true]);
    }

    /**
     * The same die, judged against the condition of successes.
     */
    public function judged(bool $success): self
    {
        return clone ($this, ['success' => $success]);
    }

    /**
     * The same die, counted as a failure.
     */
    public function failed(): self
    {
        return clone ($this, ['failure' => true]);
    }

    public function marked(Mark $mark): self
    {
        return clone ($this, ['critical' => $mark]);
    }

    public function looked(string $token): self
    {
        return clone ($this, ['look' => $token]);
    }

    /**
     * The same die with another value: its floor, its ceiling or a transformation.
     */
    public function valued(int $value): self
    {
        return $value === $this->value ? $this : clone ($this, ['value' => $value]);
    }

    /**
     * @return array{value: int, face: ?string, origin: string, kept: bool, rerolled: bool, exploded: bool, success: ?bool, failure: bool, critical: ?string, look: ?string, rolls: non-empty-list<int>}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'face' => $this->face?->id,
            'origin' => $this->origin->value,
            'kept' => $this->kept,
            'rerolled' => $this->rerolled,
            'exploded' => $this->exploded,
            'success' => $this->success,
            'failure' => $this->failure,
            'critical' => $this->critical?->value,
            'look' => $this->look,
            'rolls' => $this->rolls,
        ];
    }
}
