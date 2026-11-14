<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit\Fixture;

use Override;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Store\InMemoryLiveSessionStore;

/**
 * A store of tests that refuses the next saves, as if another request had saved the session first.
 */
final class ConflictingSessionStore implements LiveSessionStore
{
    public int $saves = 0;

    public int $pinReads = 0;

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

        return $this->inner->findByPin($pin);
    }

    #[Override]
    public function add(LiveSession $session): void
    {
        $this->inner->add($session);
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
}
