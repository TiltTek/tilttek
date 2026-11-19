<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Twig;

use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\Face;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceTower\Application\TowerEntry;

/**
 * What the overlay shows of an entry, as the Stimulus controller writes it for a new one: the roller's name when the
 * page knows it, and for each instruction its expression, its total or what it gave, and every die by the label of its
 * face — what a screen reader says — or its value, and whether it counted; an instruction that gave nothing has no
 * text. The labels and texts go through the translator of the dice, when there is one.
 */
final readonly class TowerView
{
    public function __construct(
        private CustomDice $dice,
        private ?DiceExtension $words = null,
    ) {
    }

    /**
     * The label of every face of every custom die, by die and by face.
     *
     * @return array<string, array<string, string>>
     */
    public function faces(): array
    {
        $faces = [];

        foreach ($this->dice->all() as $die) {
            $faces[$die->name] = array_merge(...array_map(fn (Face $face): array => [$face->id => $this->text($face->label)], $die->faces));
        }

        return $faces;
    }

    /**
     * @param array<string, string> $names the names of the rollers the page may show, by reference
     *
     * @return array{position: int, roller: ?string, name: ?string, results: list<array{expression: string, total: ?int, text: ?string, dice: list<array{label: string, kept: bool}>}>}
     */
    public function entry(TowerEntry $entry, array $names = []): array
    {
        $faces = $this->faces();
        $results = [];

        foreach ($entry->results as $result) {
            $dice = [];

            foreach ($result['sets'] as $set) {
                foreach ($set['dice'] as $die) {
                    $label = null === $set['die'] || null === $die['face'] ? null : ($faces[$set['die']][$die['face']] ?? null);
                    $dice[] = ['label' => $label ?? \sprintf('%d', $die['value']), 'kept' => $die['kept']];
                }
            }

            $results[] = ['expression' => $result['expression'], 'total' => $result['total'], 'text' => $this->output($result['total'], $result['output']), 'dice' => $dice];
        }

        return [
            'position' => $entry->position,
            'roller' => $entry->roller,
            'name' => null === $entry->roller ? null : ($names[$entry->roller] ?? null),
            'results' => $results,
        ];
    }

    /**
     * @param int|string|list<int|string> $output
     */
    private function output(?int $total, int|string|array $output): ?string
    {
        if (null !== $total || \is_int($output)) {
            return null;
        }

        $items = \is_array($output) ? $output : [$output];
        $text = implode(', ', array_map(fn (int|string $item): string => \is_int($item) ? \sprintf('%d', $item) : $this->text($item), $items));

        return '' === $text ? null : $text;
    }

    private function text(string $message): string
    {
        return $this->words?->text($message) ?? $message;
    }
}
