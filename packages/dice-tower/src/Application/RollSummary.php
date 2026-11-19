<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

/**
 * The least of a recorded roll that a tower shows: for each instruction, its expression, its total, what it gave, and
 * each set of dice with the custom die it names and every die — its value, its face and whether it counted. The
 * reading is bounded: it goes no deeper than `DEPTH` levels of the detail and keeps no more than `DICE` dice, and
 * skips whatever does not have the shape the journal writes.
 */
final class RollSummary
{
    public const int DEPTH = 16;

    public const int DICE = 500;

    private const string CUSTOM_DIE = '/\{([a-z][a-z0-9_-]{0,31})\}/';

    /**
     * @var list<array{notation: string, die: ?string, dice: list<array{value: int, face: ?string, kept: bool}>}>
     */
    private array $sets = [];

    private int $dice = 0;

    private function __construct()
    {
    }

    /**
     * @param list<array<string, mixed>> $result the result as the journal keeps it, in the form of `PoolResult::toArray()`
     *
     * @return list<array{expression: string, total: ?int, output: int|string|list<int|string>, sets: list<array{notation: string, die: ?string, dice: list<array{value: int, face: ?string, kept: bool}>}>}>
     */
    public static function of(array $result): array
    {
        $summary = new self();
        $instructions = [];

        foreach ($result as $instruction) {
            $summary->sets = [];
            $detail = $instruction['detail'] ?? null;

            if (\is_array($detail)) {
                $summary->read($detail, 1);
            }

            $total = $instruction['total'] ?? null;
            $expression = $instruction['expression'] ?? null;
            $instructions[] = [
                'expression' => \is_string($expression) ? $expression : '',
                'total' => \is_int($total) ? $total : null,
                'output' => self::output($instruction['output'] ?? null),
                'sets' => $summary->sets,
            ];
        }

        return $instructions;
    }

    /**
     * @return int|string|list<int|string>
     */
    private static function output(mixed $output): int|string|array
    {
        if (\is_int($output) || \is_string($output)) {
            return $output;
        }

        if (!\is_array($output)) {
            return '';
        }

        return array_values(array_filter($output, static fn (mixed $item): bool => \is_int($item) || \is_string($item)));
    }

    /**
     * @param array<mixed> $node
     */
    private function read(array $node, int $depth): void
    {
        if ($depth > self::DEPTH) {
            return;
        }

        if ('dice' === ($node['type'] ?? null) && \is_array($node['dice'] ?? null)) {
            $this->set($node, $node['dice']);
        }

        foreach ($node as $key => $child) {
            if ('dice' !== $key && \is_array($child)) {
                $this->read($child, $depth + 1);
            }
        }
    }

    /**
     * @param array<mixed> $node
     * @param array<mixed> $dice
     */
    private function set(array $node, array $dice): void
    {
        $notation = \is_string($node['notation'] ?? null) ? $node['notation'] : '';
        $read = [];

        foreach ($dice as $die) {
            if (!\is_array($die) || !\is_int($die['value'] ?? null)) {
                continue;
            }

            $face = $die['face'] ?? null;
            $read[] = ['value' => $die['value'], 'face' => \is_string($face) ? $face : null, 'kept' => true === ($die['kept'] ?? null)];
        }

        $kept = \array_slice($read, 0, self::DICE - $this->dice);
        $this->dice += \count($kept);

        $this->sets[] = ['notation' => $notation, 'die' => 1 === preg_match(self::CUSTOM_DIE, $notation, $match) ? $match[1] : null, 'dice' => $kept];
    }
}
