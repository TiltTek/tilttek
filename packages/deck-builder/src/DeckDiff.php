<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

/**
 * The differences between two versions of a deck: the cards added, removed and changed in quantity, in the order of
 * the later version then of the earlier one, and whether the name or the format changed.
 */
final readonly class DeckDiff
{
    /**
     * The cards the later version adds, each with its quantity.
     *
     * @var array<array-key, int>
     */
    public array $added;

    /**
     * The cards the later version removes, each with the quantity the earlier one held.
     *
     * @var array<array-key, int>
     */
    public array $removed;

    /**
     * The cards whose quantity changed, each with the quantity before and after.
     *
     * @var array<array-key, array{int, int}>
     */
    public array $changed;

    private function __construct(
        public Deck $from,
        public Deck $to,
    ) {
        $added = [];
        $changed = [];

        foreach ($to->cards as $card => $quantity) {
            $before = $from->quantityOf((string) $card);

            if (0 === $before) {
                $added[$card] = $quantity;
            } elseif ($before !== $quantity) {
                $changed[$card] = [$before, $quantity];
            }
        }

        $this->added = $added;
        $this->changed = $changed;
        $this->removed = array_diff_key($from->cards, $to->cards);
    }

    public static function between(Deck $from, Deck $to): self
    {
        return new self($from, $to);
    }

    public function nameChanged(): bool
    {
        return $this->from->name !== $this->to->name;
    }

    public function formatChanged(): bool
    {
        return $this->from->format !== $this->to->format;
    }

    /**
     * Whether the two versions hold the same cards in the same quantities, under the same name and format.
     */
    public function isEmpty(): bool
    {
        return [] === $this->added && [] === $this->removed && [] === $this->changed && !$this->nameChanged() && !$this->formatChanged();
    }

    /**
     * The differences as lines people read: `+2 card-a`, `-1 card-b`, `card-c: 2 → 3`, then the name and the format.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->added as $card => $quantity) {
            $lines[] = \sprintf('+%d %s', $quantity, $card);
        }

        foreach ($this->removed as $card => $quantity) {
            $lines[] = \sprintf('-%d %s', $quantity, $card);
        }

        foreach ($this->changed as $card => [$before, $after]) {
            $lines[] = \sprintf('%s: %d → %d', $card, $before, $after);
        }

        if ($this->nameChanged()) {
            $lines[] = \sprintf('Name: "%s" → "%s"', $this->from->name, $this->to->name);
        }

        if ($this->formatChanged()) {
            $lines[] = \sprintf('Format: %s → %s', $this->from->format, $this->to->format);
        }

        return $lines;
    }
}
