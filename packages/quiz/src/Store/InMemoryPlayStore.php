<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Store;

use Override;
use Tilttek\Quiz\Contract\PlayStore;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\SelfPaced\Play;

/**
 * The plays in memory, for tests and for a game that lives in one process. It keeps copies, so that a play changed
 * and not saved leaves the stored one as it was.
 */
final class InMemoryPlayStore implements PlayStore
{
    /** @var array<string, Play> */
    private array $plays = [];

    #[Override]
    public function find(string $play): ?Play
    {
        return isset($this->plays[$play]) ? clone $this->plays[$play] : null;
    }

    #[Override]
    public function countOf(string $quiz, string $player): int
    {
        return \count(array_filter($this->plays, static fn (Play $play): bool => $play->quiz === $quiz && $play->player->id === $player));
    }

    #[Override]
    public function add(Play $play): void
    {
        if (isset($this->plays[$play->id])) {
            throw QuizConflict::at('play', $play->id, 0, $this->plays[$play->id]->version());
        }

        foreach ($this->plays as $stored) {
            if ($stored->quiz === $play->quiz && $stored->player->id === $play->player->id && $stored->attempt === $play->attempt) {
                throw QuizConflict::attempt($play->quiz, $play->player->id, $play->attempt);
            }
        }

        $this->plays[$play->id] = clone $play;
    }

    #[Override]
    public function save(Play $play): void
    {
        $stored = $this->plays[$play->id] ?? null;

        if ($stored?->version() !== $play->version() - 1) {
            throw QuizConflict::at('play', $play->id, $play->version() - 1, $stored?->version());
        }

        $this->plays[$play->id] = clone $play;
    }

    #[Override]
    public function erasePlayer(string $player): int
    {
        $before = \count($this->plays);
        $this->plays = array_filter($this->plays, static fn (Play $play): bool => $play->player->id !== $player);

        return $before - \count($this->plays);
    }
}
