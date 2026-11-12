<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Visibility;

/**
 * The choices of appearance in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryAppearanceStore implements AppearanceStore
{
    /**
     * @var array<string, Appearance>
     */
    private array $appearances = [];

    #[Override]
    public function choose(ResourceReference $member, Appearance $appearance): void
    {
        unset($this->appearances[$member->toString()]);

        if (Visibility::Hidden !== $appearance->visibility) {
            $this->appearances[$member->toString()] = $appearance;
        }
    }

    #[Override]
    public function of(array $members): array
    {
        $chosen = [];

        foreach ($members as $member) {
            if (isset($this->appearances[$member->toString()])) {
                $chosen[$member->toString()] = $this->appearances[$member->toString()];
            }
        }

        return $chosen;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->appearances);
        unset($this->appearances[$member->toString()]);

        return $count - \count($this->appearances);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->appearances = [];
    }
}
