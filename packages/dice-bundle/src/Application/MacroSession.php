<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Closure;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;

/**
 * The macros one input expands, created for that input alone: each macro parsed once, the macros being expanded, to
 * refuse a macro that names itself, and the expansions counted — those of a macro within a macro once for each time —
 * against their limit.
 *
 * @internal
 */
final class MacroSession
{
    private int $expansions = 0;

    /**
     * The macros being expanded, by their key.
     *
     * @var list<string>
     */
    private array $open = [];

    /**
     * Each macro expanded, by its key: its pool and the expansions it counts.
     *
     * @var array<string, array{Pool, int}>
     */
    private array $done = [];

    public function __construct(
        private readonly int $limit,
    ) {
    }

    /**
     * @param string          $key        what identifies the macro
     * @param string          $name       its name
     * @param string          $expression its expression
     * @param Closure(): Pool $parse      parses its expression
     *
     * @throws InvalidExpression when the macro names itself, or does not parse
     * @throws LimitExceeded     when the input expands too many macros
     */
    public function expand(string $key, string $name, string $expression, Closure $parse): Pool
    {
        if (\array_key_exists($key, $this->done)) {
            [$pool, $weight] = $this->done[$key];
            $this->count($weight);

            return $pool;
        }

        if (\in_array($key, $this->open, true)) {
            throw InvalidExpression::at($expression, 0, \sprintf('The macro "%s" names itself', $name));
        }

        $this->open[] = $key;
        $before = $this->expansions;
        $pool = $parse();
        array_pop($this->open);
        $this->count(1);
        $this->done[$key] = [$pool, $this->expansions - $before];

        return $pool;
    }

    private function count(int $weight): void
    {
        $this->expansions += $weight;

        if ($this->expansions > $this->limit) {
            throw LimitExceeded::of('macros an input expands', $this->limit, $this->expansions);
        }
    }
}
