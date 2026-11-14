<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Tracery;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * The variables each generator of a grammar reads without setting them first, which the rule that draws it gives:
 * computed until no generator reads more, since a generator receives what the generators it draws read.
 *
 * @internal
 */
final class FreeVariables
{
    /**
     * @var array<string, true> every variable an action sets
     */
    private array $variables = [];

    /**
     * @var array<array-key, array<string, true>> by symbol, the variables it receives
     */
    private array $free = [];

    /**
     * @var array<array-key, list<array{string, array<string, true>}>> by symbol, the generators its rules draw, each with
     *                                                                 the variables set before it is drawn
     */
    private array $calls = [];

    /**
     * @param array<array-key, list<list<Text|Tag|Action>>> $rules      by symbol
     * @param array<string, true>                           $generators the symbols that become generators
     *
     * @throws InvalidDefinition when a tag names neither a symbol nor a variable
     */
    public function __construct(private readonly array $rules, private readonly array $generators)
    {
        foreach ($rules as $symbolRules) {
            foreach ($symbolRules as $rule) {
                $this->collect($rule);
            }
        }

        $callers = [];

        foreach ($rules as $symbol => $symbolRules) {
            $this->free[$symbol] = [];
            $this->calls[$symbol] = [];

            foreach ($symbolRules as $rule) {
                $local = [];
                $this->reads($rule, $local, $symbol, 'tracery > '.$symbol);
            }

            foreach ($this->calls[$symbol] as [$callee]) {
                $callers[$callee][$symbol] = true;
            }
        }

        // A symbol is looked at again only when a generator it draws receives more.
        $queue = array_fill_keys(array_keys($rules), true);

        while ([] !== $queue) {
            $symbol = (string) array_key_first($queue);
            unset($queue[$symbol]);
            $free = $this->free[$symbol];

            foreach ($this->calls[$symbol] as [$callee, $local]) {
                $free += array_diff_key($this->free[$callee], $local);
            }

            if (\count($free) > \count($this->free[$symbol])) {
                $this->free[$symbol] = $free;
                $queue += $callers[$symbol] ?? [];
            }
        }
    }

    /**
     * The variables a symbol receives, in order of their names; none for a list.
     *
     * @return list<string>
     */
    public function of(string $symbol): array
    {
        $variables = array_map(strval(...), array_keys($this->free[$symbol] ?? []));
        sort($variables);

        return $variables;
    }

    /**
     * Whether a name is a symbol of the grammar.
     */
    public function isSymbol(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * Whether a symbol becomes a generator.
     */
    public function isGenerator(string $symbol): bool
    {
        return isset($this->generators[$symbol]);
    }

    /**
     * @param list<Text|Tag|Action> $segments
     */
    private function collect(array $segments): void
    {
        foreach ($segments as $segment) {
            foreach ($this->actions($segment) as $action) {
                $this->variables[$action->name] = true;

                foreach ($action->values as $value) {
                    $this->collect($value);
                }
            }
        }
    }

    /**
     * Notes the variables a rule reads before it sets them, and the generators it draws with the variables set before.
     *
     * @param list<Text|Tag|Action> $segments
     * @param array<string, true>   $local
     */
    private function reads(array $segments, array &$local, int|string $symbol, string $at): void
    {
        foreach ($segments as $segment) {
            foreach (self::actions($segment) as $action) {
                foreach ($action->values as $value) {
                    $this->reads($value, $local, $symbol, $at);
                }

                $local[$action->name] = true;
            }

            if (!$segment instanceof Tag || '' === $segment->symbol || isset($local[$segment->symbol])) {
                continue;
            }

            if ($this->isGenerator($segment->symbol)) {
                $this->calls[$symbol][] = [$segment->symbol, $local];
            } elseif (!$this->isSymbol($segment->symbol)) {
                if (!isset($this->variables[$segment->symbol])) {
                    throw InvalidDefinition::at($at, \sprintf('the symbol "%s" does not exist', $segment->symbol));
                }

                $this->free[$symbol][$segment->symbol] = true;
            }
        }
    }

    /**
     * The actions a segment runs: an action itself, or the actions of a tag.
     *
     * @return list<Action>
     */
    private function actions(Text|Tag|Action $segment): array
    {
        return match (true) {
            $segment instanceof Action => [$segment],
            $segment instanceof Tag => $segment->actions,
            default => [],
        };
    }
}
