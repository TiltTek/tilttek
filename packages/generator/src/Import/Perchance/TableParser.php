<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\Reader;

/**
 * Reads tables in the subset of Perchance's syntax without JavaScript into their sections and entries.
 *
 * @internal
 */
final readonly class TableParser
{
    /**
     * The most sections, and the most entries of a section.
     */
    public const int MAX_SECTIONS = 4096;

    /**
     * The deepest nesting of choices in an entry.
     */
    public const int MAX_DEPTH = 10;

    /**
     * The most entries `selectUnique()` and `selectMany()` take.
     */
    public const int MAX_SELECT = 100;

    /**
     * The heaviest weight.
     */
    public const int MAX_WEIGHT = 1_000_000;

    private const string DICE = '/(?<![\w.])(\d{0,3}d\d{1,4}(?:[+-]\d{1,6})?)(?!\w)/';

    private DiceRoller $dice;

    public function __construct(private string $prefix = '')
    {
        $this->dice = new DiceRoller();
    }

    /**
     * The entries of each section, in the order of the sections; a section without entries has an empty list, and
     * `title` is set aside.
     *
     * @return array<array-key, list<Branch>> by name, a name made of digits being a key of integer
     *
     * @throws InvalidDefinition
     */
    public function parse(string $text): array
    {
        $sections = [];

        foreach ($this->sections($this->lines($text)) as $name => $entries) {
            $sections[$name] = array_map(fn (array $entry): Branch => $this->entry($entry[1], 'perchance > line '.$entry[0]), $entries);
        }

        return $sections;
    }

    /**
     * A section's name as the library names it: in lower case, its spaces read as hyphens.
     */
    public function name(string $name, string $at): string
    {
        $name = (string) preg_replace('/\s+/', '-', mb_strtolower(trim($name)));

        if (!Reader::isName($this->prefix.$name)) {
            throw InvalidDefinition::at($at, \sprintf('"%s" is not a name: letters, digits, "_" and "-", in parts joined by dots', $name));
        }

        return $name;
    }

    /**
     * The lines of the tables, by number: those of the fenced blocks marked `perhaps` or `perchance` when there are
     * any, otherwise all of them.
     *
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        $numbered = [];

        $lines = preg_split('/\r\n|\r|\n/', $text);

        foreach (false === $lines ? [] : $lines as $index => $line) {
            $numbered[$index + 1] = $line;
        }

        $fenced = [];
        $inside = false;
        $found = false;

        foreach ($numbered as $number => $line) {
            if (!$inside && 1 === preg_match('/^\s*(```|~~~)\s*(perhaps|perchance)\s*$/i', $line)) {
                $inside = true;
                $found = true;
            } elseif ($inside && 1 === preg_match('/^\s*(```|~~~)\s*$/', $line)) {
                $inside = false;
            } elseif ($inside) {
                $fenced[$number] = $line;
            }
        }

        // Once a block has been found, only blocks are read.
        return $found ? $fenced : $numbered;
    }

    /**
     * The entries of each section with their line, in the order of the sections.
     *
     * @param array<int, string> $lines
     *
     * @return array<string, list<array{int, string}>>
     */
    private function sections(array $lines): array
    {
        $sections = [];
        $current = null;

        foreach ($lines as $number => $line) {
            $line = rtrim($this->uncomment($line));
            $at = 'perchance > line '.$number;

            if ('' === $line) {
                continue;
            }

            if (1 === preg_match('/^(\t| {2,})/', $line)) {
                if (null === $current) {
                    throw InvalidDefinition::at($at, 'an entry is under a section');
                }

                if (\count($sections[$current]) >= self::MAX_SECTIONS) {
                    throw InvalidDefinition::at($at, \sprintf('a section has at most %d entries', self::MAX_SECTIONS));
                }

                $sections[$current][] = [$number, trim($line)];

                continue;
            }

            if (' ' === $line[0]) {
                throw InvalidDefinition::at($at, 'an entry is indented by a tab or two spaces');
            }

            $current = $this->name($line, $at);

            if (!isset($sections[$current]) && 'title' !== $current && \count($sections) - (int) isset($sections['title']) >= self::MAX_SECTIONS) {
                throw InvalidDefinition::at($at, \sprintf('the tables have at most %d sections', self::MAX_SECTIONS));
            }

            $sections[$current] ??= [];
        }

        unset($sections['title']);

        return $sections;
    }

    /**
     * The line without its comment: `//` at its start or after a space or a tab.
     */
    private function uncomment(string $line): string
    {
        return 1 === preg_match('/(^|[ \t])\/\//', $line, $match, \PREG_OFFSET_CAPTURE) ? substr($line, 0, $match[0][1]) : $line;
    }

    /**
     * An entry's segments and its weight, from `^weight` at its end.
     */
    private function entry(string $entry, string $at): Branch
    {
        [$text, $weight] = $this->weighed($entry, $at);
        $position = 0;

        return new Branch($this->segments($text, $position, [], 0, $at), $weight);
    }

    /**
     * The text and its weight, a positive number of at most a million, which keeps three digits after the point.
     *
     * @return array{string, string}
     */
    private function weighed(string $text, string $at): array
    {
        if (1 !== preg_match('/(?<!\\\\)\^\s*([^\s^{}\[\]|]+)\s*$/D', $text, $match, \PREG_OFFSET_CAPTURE)) {
            return [$text, '1'];
        }

        $weight = $match[1][0];

        if (1 !== preg_match('/^\d{1,7}(\.\d+)?$/D', $weight) || 0.0 >= (float) $weight || (float) $weight > self::MAX_WEIGHT) {
            throw InvalidDefinition::at($at, \sprintf('"%s" is not a weight: a positive number of at most %d', $weight, self::MAX_WEIGHT));
        }

        return [rtrim(substr($text, 0, $match[0][1])), $weight];
    }

    /**
     * The segments of a text up to one of the stops, which the caller then reads.
     *
     * @param list<string> $stops
     *
     * @return list<Text|Dice|Range|Reference|Choice>
     */
    private function segments(string $text, int &$position, array $stops, int $depth, string $at): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidDefinition::at($at, \sprintf('choices nest at most %d deep', self::MAX_DEPTH));
        }

        $segments = [];
        $plain = '';
        $length = \strlen($text);

        while ($position < $length && !\in_array($text[$position], $stops, true)) {
            $character = $text[$position++];

            if ('[' !== $character && '{' !== $character && '\\' !== $character) {
                $plain .= $character;

                continue;
            }

            array_push($segments, ...$this->plain($plain));
            $plain = '';
            $segments[] = match ($character) {
                '\\' => new Text($position < $length ? $text[$position++] : '\\', true),
                '[' => $this->reference($text, $position, $at),
                default => $this->brace($text, $position, $depth + 1, $at),
            };
        }

        return [...$segments, ...$this->plain($plain)];
    }

    /**
     * Plain text, with the rolls of dice it holds apart.
     *
     * @return list<Text|Dice>
     */
    private function plain(string $text): array
    {
        $segments = [];

        $parts = preg_split(self::DICE, $text, -1, \PREG_SPLIT_DELIM_CAPTURE);

        foreach (false === $parts ? [] : $parts as $index => $part) {
            if ('' !== $part) {
                $segments[] = 1 === $index % 2 && $this->rolls($part) ? new Dice($part) : new Text($part);
            }
        }

        return $segments;
    }

    private function rolls(string $expression): bool
    {
        try {
            $this->dice->parse($expression);

            return true;
        } catch (DiceException) {
            return false;
        }
    }

    /**
     * `[name]`, `[name.selectUnique(n)]` or `[name.selectMany(n)]`, read after its bracket and up to its closing one.
     */
    private function reference(string $text, int &$position, string $at): Reference
    {
        $close = strpos($text, ']', $position);

        if (false === $close) {
            throw InvalidDefinition::at($at, '"[" is not closed');
        }

        $inside = trim(substr($text, $position, $close - $position));
        $position = $close + 1;

        if (1 !== preg_match('/^([^.\[\]{}()]+?)(?:\s*\.\s*(selectUnique|selectMany)\s*\(\s*(\d{1,3})\s*\))?$/D', $inside, $match)) {
            throw InvalidDefinition::at($at, \sprintf('"[%s]" is not supported: a reference is [name], [name.selectUnique(n)] or [name.selectMany(n)]', $inside));
        }

        if (!isset($match[3])) {
            return new Reference($this->name($match[1], $at));
        }

        $count = (int) $match[3];

        if ($count < 1 || $count > self::MAX_SELECT) {
            throw InvalidDefinition::at($at, \sprintf('a selection takes from 1 to %d entries', self::MAX_SELECT));
        }

        return new Reference($this->name($match[1], $at), $match[2], $count);
    }

    /**
     * `{a|b^2|c}` or `{1-6}`, read after its brace and up to its closing one.
     */
    private function brace(string $text, int &$position, int $depth, string $at): Range|Choice
    {
        $close = strpos($text, '}', $position);

        if (false !== $close && 1 === preg_match('/^\s*(\d{1,9})\s*-\s*(\d{1,9})\s*$/D', substr($text, $position, $close - $position), $match)) {
            $position = $close + 1;

            if ((int) $match[1] > (int) $match[2]) {
                throw InvalidDefinition::at($at, \sprintf('the range {%s-%s} is empty', $match[1], $match[2]));
            }

            return new Range((int) $match[1], (int) $match[2]);
        }

        $branches = [];

        do {
            $segments = $this->segments($text, $position, ['|', '}'], $depth, $at);

            if ($position >= \strlen($text)) {
                throw InvalidDefinition::at($at, '"{" is not closed');
            }

            $branches[] = $this->branch($segments, $at);
        } while ('|' === $text[$position++]);

        if (\count($branches) < 2) {
            throw InvalidDefinition::at($at, 'a choice has at least two parts: {a|b}');
        }

        return new Choice($branches);
    }

    /**
     * A part of a choice and its weight, from `^weight` at the end of its last plain text.
     *
     * @param list<Text|Dice|Range|Reference|Choice> $segments
     */
    private function branch(array $segments, string $at): Branch
    {
        $last = array_last($segments);

        if (!$last instanceof Text || $last->kept) {
            return new Branch($segments);
        }

        [$text, $weight] = $this->weighed($last->text, $at);
        array_pop($segments);

        return new Branch('' === $text ? $segments : [...$segments, new Text($text)], $weight);
    }
}
