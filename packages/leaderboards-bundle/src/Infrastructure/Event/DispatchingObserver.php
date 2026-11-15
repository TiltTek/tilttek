<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

use Override;
use Phil\Resource\ResourceReference;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\LeaderboardObserver;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\LeagueObserver;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Store\AppearanceStore;

/**
 * Turns what the library tells into events of the application's event dispatcher: `SnapshotTaken` and
 * `LeagueSettled`, each followed by a `TopReached` for every member who reached the top — first of the snapshot, when
 * the first places are celebrated once the period closed, or promoted into the highest tier of the league, which the
 * boards tell —, carrying how the member chose to appear.
 */
final readonly class DispatchingObserver implements LeaderboardObserver, LeagueObserver
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ?Boards $boards = null,
        private CelebrateOn $celebrateOn = CelebrateOn::PeriodClose,
        private ?AppearanceStore $appearances = null,
    ) {
    }

    #[Override]
    public function snapshotTaken(Snapshot $snapshot): void
    {
        $this->dispatcher->dispatch(new SnapshotTaken($snapshot));

        if (CelebrateOn::PeriodClose !== $this->celebrateOn) {
            return;
        }

        foreach ($snapshot->placings as $placing) {
            $summit = Summit::ofPlacing($placing);

            if ($summit instanceof Summit) {
                $this->dispatcher->dispatch(new TopReached($placing->board, $placing->period, $placing->member, $summit, $snapshot->takenAt, $this->appearance($placing->member)));
            }
        }
    }

    #[Override]
    public function leagueSettled(Settlement $settlement): void
    {
        $this->dispatcher->dispatch(new LeagueSettled($settlement));
        $league = $this->league($settlement->board);

        if (!$league instanceof League) {
            return;
        }

        foreach ($settlement->memberships as $membership) {
            $summit = Summit::ofMembership($membership, $league->tiers);

            if ($summit instanceof Summit) {
                $this->dispatcher->dispatch(new TopReached($membership->board, $membership->week, $membership->member, $summit, $settlement->settledAt, $this->appearance($membership->member)));
            }
        }
    }

    private function league(string $board): ?League
    {
        return $this->boards instanceof Boards && $this->boards->has($board) ? $this->boards->get($board)->league : null;
    }

    private function appearance(ResourceReference $member): Appearance
    {
        return $this->appearances?->of([$member])[$member->toString()] ?? Appearance::hidden();
    }
}
