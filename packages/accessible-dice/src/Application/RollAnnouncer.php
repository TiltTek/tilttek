<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Notation\NotationWriter;
use Tilttek\Dice\Result\Outputs;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\RolledDie;

/**
 * A roll in the sentence a screen reader announces: for each instruction, its expression, every die by the label of
 * its face or its value — a die that did not count said so —, then its total or what it gave; the roller's name first
 * when the page gives one.
 */
final readonly class RollAnnouncer
{
    public function __construct(
        private Words $words = new Words(),
    ) {
    }

    public function announce(PoolResult $result, ?string $name = null, ?string $locale = null): string
    {
        $writer = new NotationWriter();
        $lines = [];

        foreach ($result->results as $instruction) {
            $dice = [];

            foreach ($instruction->result->sets() as $set) {
                foreach ($set->dice as $die) {
                    $dice[] = $this->die($die, $locale);
                }
            }

            $expression = $writer->writeNode($instruction->expression);
            $outcome = $this->outcome($instruction->total(), $instruction->output(), $locale);
            $lines[] = [] === $dice
                ? $this->words->word('roll.without_dice', ['%expression%' => $expression, '%outcome%' => $outcome], $locale)
                : $this->words->word('roll.with_dice', ['%expression%' => $expression, '%dice%' => implode(', ', $dice), '%outcome%' => $outcome], $locale);
        }

        $roll = implode(' ', $lines);

        return null === $name ? $roll : $this->words->word('roll.by', ['%name%' => $name, '%roll%' => $roll], $locale);
    }

    private function die(RolledDie $die, ?string $locale): string
    {
        $word = $die->face instanceof Face ? $this->words->label($die->face->label, $locale) : \sprintf('%d', $die->value);

        return $die->kept ? $word : $this->words->word('roll.dropped', ['%die%' => $word], $locale);
    }

    /**
     * @param int|string|list<int|string> $output
     */
    private function outcome(?int $total, int|string|array $output, ?string $locale): string
    {
        if (null !== $total) {
            return $this->words->word('roll.total', ['%total%' => $total], $locale);
        }

        $text = implode(', ', array_map(fn (int|string $item): string => \is_int($item) ? \sprintf('%d', $item) : $this->words->label($item, $locale), Outputs::items($output)));

        return '' === $text ? $this->words->word('roll.nothing', [], $locale) : $this->words->word('roll.gave', ['%text%' => $text], $locale);
    }
}
