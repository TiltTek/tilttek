<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Tracery;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * Reads a rule of a Tracery grammar into its texts, tags and actions, a backslash keeping the next character as written.
 *
 * @internal
 */
final readonly class RuleParser
{
    /**
     * The deepest nesting of actions and tags in a rule.
     */
    public const int MAX_DEPTH = 16;

    /**
     * The modifiers, and the filters they become.
     */
    public const array MODIFIERS = ['capitalize' => 'upper_first', 'capitalizeAll' => 'title', 'a' => "article('indefinite')", 's' => 'plural'];

    public const string SYMBOL = '/^[A-Za-z0-9_-]+$/D';

    private const string VARIABLE = '/^[A-Za-z0-9_]+$/D';

    /**
     * @return list<Text|Tag|Action>
     *
     * @throws InvalidDefinition
     */
    public function parse(string $rule, string $at): array
    {
        $position = 0;

        return $this->rule($rule, $position, [], 0, $at);
    }

    /**
     * The segments of a rule, read up to one of the stops, which the caller then reads.
     *
     * @param list<string> $stops
     *
     * @return list<Text|Tag|Action>
     */
    private function rule(string $text, int &$position, array $stops, int $depth, string $at): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidDefinition::at($at, \sprintf('actions and tags nest at most %d deep', self::MAX_DEPTH));
        }

        $segments = [];
        $plain = '';
        $length = \strlen($text);

        while ($position < $length && !\in_array($text[$position], $stops, true)) {
            $character = $text[$position++];

            if ('\\' === $character) {
                $plain .= $position < $length ? $text[$position++] : '\\';

                continue;
            }

            if ('[' !== $character && '#' !== $character) {
                $plain .= $character;

                continue;
            }

            if ('' !== $plain) {
                $segments[] = new Text($plain);
                $plain = '';
            }

            $segments[] = '[' === $character ? $this->action($text, $position, $depth + 1, $at) : $this->tag($text, $position, $depth + 1, $at);
        }

        if ([] !== $stops && $position >= $length) {
            throw InvalidDefinition::at($at, '"[" is not closed');
        }

        if ('' !== $plain) {
            $segments[] = new Text($plain);
        }

        return $segments;
    }

    /**
     * `[name:#rule#]` or `[name:one,other]`, read after its opening bracket and up to its closing one.
     */
    private function action(string $text, int &$position, int $depth, string $at): Action
    {
        $colon = strpos($text, ':', $position);
        $close = strpos($text, ']', $position);

        if (false === $colon || (false !== $close && $close < $colon)) {
            throw InvalidDefinition::at($at, 'an action is "[name:rule]"; other actions are not supported');
        }

        $name = substr($text, $position, $colon - $position);

        if (1 !== preg_match(self::VARIABLE, $name)) {
            throw InvalidDefinition::at($at, \sprintf('"%s" is not a name of a variable: letters, digits and "_"', $name));
        }

        $position = $colon + 1;

        if (str_starts_with(substr($text, $position), 'POP]')) {
            throw InvalidDefinition::at($at, 'POP is not supported: a variable keeps its value for the rest of its rule');
        }

        $values = [$this->rule($text, $position, [',', ']'], $depth, $at)];

        while (',' === $text[$position++]) {
            $values[] = $this->rule($text, $position, [',', ']'], $depth, $at);
        }

        return new Action($name, $values);
    }

    /**
     * `#symbol.modifier#`, `#[name:#rule#]symbol#`, read after its opening hash and up to its closing one.
     */
    private function tag(string $text, int &$position, int $depth, string $at): Tag
    {
        $actions = [];

        while ('[' === ($text[$position] ?? null)) {
            ++$position;
            $actions[] = $this->action($text, $position, $depth + 1, $at);
        }

        $close = strpos($text, '#', $position);

        if (false === $close) {
            throw InvalidDefinition::at($at, '"#" is not closed');
        }

        $modifiers = explode('.', substr($text, $position, $close - $position));
        $position = $close + 1;
        $symbol = array_shift($modifiers);

        if ('' !== $symbol && 1 !== preg_match(self::SYMBOL, $symbol)) {
            throw InvalidDefinition::at($at, \sprintf('"%s" is not a symbol', $symbol));
        }

        foreach ($modifiers as $modifier) {
            if (!isset(self::MODIFIERS[$modifier])) {
                throw InvalidDefinition::at($at, \sprintf('the modifier "%s" is not supported; the modifiers are "%s"', $modifier, implode('", "', array_keys(self::MODIFIERS))));
            }
        }

        if ('' === $symbol && [] !== $modifiers) {
            throw InvalidDefinition::at($at, 'a modifier follows a symbol');
        }

        return new Tag($actions, $symbol, $modifiers);
    }
}
