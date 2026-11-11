<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Memory;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;

/**
 * The macros in memory, for tests and for applications that keep none beyond the request.
 */
final class InMemoryDiceMacroRepository implements DiceMacroRepository
{
    /**
     * @var array<string, DiceMacro>
     */
    private array $macros = [];

    #[Override]
    public function get(MacroLevel $level, ResourceReference $owner, string $name): ?DiceMacro
    {
        return $this->macros[$this->key($level, $owner, $name)] ?? null;
    }

    #[Override]
    public function save(DiceMacro $macro): void
    {
        $this->macros[$this->key($macro->level(), $macro->owner(), $macro->name())] = $macro;
    }

    #[Override]
    public function remove(DiceMacro $macro): void
    {
        unset($this->macros[$this->key($macro->level(), $macro->owner(), $macro->name())]);
    }

    #[Override]
    public function ofOwner(MacroLevel $level, ResourceReference $owner): array
    {
        $macros = array_filter($this->macros, static fn (DiceMacro $macro): bool => $level === $macro->level() && $macro->owner()->equals($owner));
        usort($macros, static fn (DiceMacro $a, DiceMacro $b): int => strcmp($a->name(), $b->name()));

        return $macros;
    }

    #[Override]
    public function eraseOwner(ResourceReference $owner): int
    {
        $erased = array_filter($this->macros, static fn (DiceMacro $macro): bool => $macro->owner()->equals($owner));
        $this->macros = array_diff_key($this->macros, $erased);

        return \count($erased);
    }

    /**
     * Empties the repository: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->macros = [];
    }

    private function key(MacroLevel $level, ResourceReference $owner, string $name): string
    {
        return implode("\n", [$level->value, $owner->toString(), $name]);
    }
}
