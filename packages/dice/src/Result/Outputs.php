<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

/**
 * What results give, written as text or flattened into lists.
 */
final class Outputs
{
    private function __construct()
    {
    }

    /**
     * An output as a sentence writes it: a number in digits, a text as it is, a list's items separated by a comma
     * and a space.
     *
     * @param int|string|list<int|string> $output
     */
    public static function text(int|string|array $output): string
    {
        return \is_array($output) ? implode(', ', $output) : (string) $output;
    }

    /**
     * An output as items of a list: a list's own items, or the number or text alone.
     *
     * @param int|string|list<int|string> $output
     *
     * @return list<int|string>
     */
    public static function items(int|string|array $output): array
    {
        return \is_array($output) ? $output : [$output];
    }
}
