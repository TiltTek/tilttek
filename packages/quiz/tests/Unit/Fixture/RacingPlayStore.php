<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Tilttek\Quiz\Contract\PlayStore;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\Store\InMemoryPlayStore;

/**
 * A store of tests where another play of the member is started between each count and the add that follows it, as
 * many times as `$races` says: each count it gives is one short.
 */
final class RacingPlayStore implements PlayStore
{
    public int $counts = 0;

    public function __construct(
        public readonly InMemoryPlayStore $inner,
        private int $races,
    ) {
    }

    #[Override]
    public function find(string $play): ?Play
    {
        return $this->inner->find($play);
    }

    #[Override]
    public function countOf(string $quiz, string $player): int
    {
        ++$this->counts;
        $count = $this->inner->countOf($quiz, $player);

        if ($this->races > 0) {
            --$this->races;
            $this->inner->add(Play::start('racing-'.$this->counts, Quizzes::trivia(), Player::member($player), new DateTimeImmutable('2026-11-12 20:00:00 UTC'), $count + 1));
        }

        return $count;
    }

    #[Override]
    public function add(Play $play): void
    {
        $this->inner->add($play);
    }

    #[Override]
    public function save(Play $play): void
    {
        $this->inner->save($play);
    }

    #[Override]
    public function erasePlayer(string $player): int
    {
        return $this->inner->erasePlayer($player);
    }
}
