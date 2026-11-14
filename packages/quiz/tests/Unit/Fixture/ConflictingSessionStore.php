<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit\Fixture;

use Closure;
use Override;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Store\InMemoryLiveSessionStore;

/**
 * A store of tests that refuses the next saves, as if another request had saved the session first, and that may run
 * another request once, just after it next looks a PIN up — the answer read already — or just before it adds a
 * session, as if both had come at once.
 */
final class ConflictingSessionStore implements LiveSessionStore
{
    public int $saves = 0;

    public int $pinReads = 0;

    public int $adds = 0;

    /** @var (Closure(): void)|null run once, after the next PIN looked up or before the next session added */
    public ?Closure $meanwhile = null;

    public function __construct(
        public readonly InMemoryLiveSessionStore $inner = new InMemoryLiveSessionStore(),
        public int $conflicts = 0,
    ) {
    }

    #[Override]
    public function find(string $session): ?LiveSession
    {
        return $this->inner->find($session);
    }

    #[Override]
    public function findByPin(string $pin): ?LiveSession
    {
        ++$this->pinReads;
        $found = $this->inner->findByPin($pin);
        $this->runMeanwhile();

        return $found;
    }

    #[Override]
    public function add(LiveSession $session): bool
    {
        ++$this->adds;
        $this->runMeanwhile();

        return $this->inner->add($session);
    }

    #[Override]
    public function save(LiveSession $session): void
    {
        ++$this->saves;

        if ($this->conflicts > 0) {
            --$this->conflicts;

            throw QuizConflict::at('live session', $session->id, $session->version() - 1, $session->version());
        }

        $this->inner->save($session);
    }

    private function runMeanwhile(): void
    {
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }
    }
}
