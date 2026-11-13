<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

/**
 * The rules of a format on a day: the format's own lists, with the taboo list that applies on that day added to them.
 * A card the taboo list restricts takes the taboo list's number in place of the format's.
 */
final readonly class Ruling
{
    /** @var array<string, string> */
    private array $banned;

    public function __construct(
        public Format $format,
        public ?TabooList $tabooList = null,
    ) {
        $banned = array_fill_keys($format->banned, $this->formatLabel());

        if ($tabooList instanceof TabooList) {
            foreach ($tabooList->banned as $card) {
                $banned[$card] ??= $this->tabooLabel($tabooList);
            }
        }

        $this->banned = $banned;
    }

    /**
     * What bans the card — the format or the taboo list, as messages show it —, or null when the card is not banned.
     */
    public function bannedBy(string $card): ?string
    {
        return $this->banned[$card] ?? null;
    }

    /**
     * The copies of the card a deck may hold: none when it is banned, otherwise the card's own number or the
     * format's, lowered by a restriction.
     */
    public function copyLimit(Card $card): int
    {
        if (null !== $this->bannedBy($card->id)) {
            return 0;
        }

        $base = $card->copyLimit ?? $this->format->copies;
        $restriction = $this->restriction($card->id);

        return null === $restriction ? $base : min($base, $restriction[0]);
    }

    /**
     * What restricts the card below its own number or the format's, as messages show it, or null when no restriction
     * lowers it.
     */
    public function restrictedBy(Card $card): ?string
    {
        $restriction = $this->restriction($card->id);

        if (null === $restriction || $restriction[0] >= ($card->copyLimit ?? $this->format->copies)) {
            return null;
        }

        return $restriction[1];
    }

    /**
     * @return array{int, string}|null
     */
    private function restriction(string $card): ?array
    {
        if ($this->tabooList instanceof TabooList && isset($this->tabooList->restricted[$card])) {
            return [$this->tabooList->restricted[$card], $this->tabooLabel($this->tabooList)];
        }

        if (isset($this->format->restricted[$card])) {
            return [$this->format->restricted[$card], $this->formatLabel()];
        }

        return null;
    }

    private function formatLabel(): string
    {
        return \sprintf('the format "%s"', $this->format->code);
    }

    private function tabooLabel(TabooList $list): string
    {
        return \sprintf('the taboo list "%s" from %s', $list->code, $list->effectiveFrom->format('Y-m-d'));
    }
}
