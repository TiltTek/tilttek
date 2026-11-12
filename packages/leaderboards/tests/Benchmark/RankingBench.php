<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use LogicException;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\RespConnection;

/**
 * The reads of a ranking a page makes at each display: its first ten lines, a member's rank, how many are ranked, and
 * the first lines among a circle of thirty — a division —, on boards of 1 000 and 10 000 members with two tie-breaks.
 * The store in memory orders its scores at each read; Valkey reads its sorted set by its index, when
 * `PHIL_TEST_VALKEY_URL` names a server, whose keys under `{bench-*}` it keeps from one run to the next.
 *
 * @BeforeMethods("setUp")
 */
final class RankingBench
{
    private ScoreStore $store;

    private Ranking $ranking;

    private Score $member;

    private Circle $division;

    public function __construct()
    {
        $this->store = new InMemoryScoreStore();
        $this->ranking = new Ranking();
        $this->member = new Score('games', '2026-11', ResourceReference::of('member', '0'), 0, new DateTimeImmutable('2026-11-01 00:00 UTC'));
        $this->division = Circle::of('member:0');
    }

    /**
     * @param array{store: string, members: int} $params
     */
    public function setUp(array $params): void
    {
        $boards = Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played'], 'tie_breaks' => [['measure' => 'count', 'order' => 'asc'], ['measure' => 'highest']]]]);
        $this->ranking = $boards->get('games')->ranking;

        $this->store = 'valkey' === $params['store']
            ? new ValkeyScoreStore(new RespConnection((string) getenv('PHIL_TEST_VALKEY_URL')), $boards, 'bench-'.$params['members'])
            : new InMemoryScoreStore();
        $at = new DateTimeImmutable('2026-11-01 00:00 UTC');

        // The sorted sets of a run before are kept: each iteration runs in a process of its own.
        for ($index = $this->store->participants('games', '2026-11'); $index < $params['members']; ++$index) {
            $this->store->save(new Score('games', '2026-11', ResourceReference::of('member', (string) $index), ($index * 7919) % 5000, $at->modify(\sprintf('+%d seconds', $index)), [$index % 13, $index % 17]));
        }

        $this->member = $this->store->score('games', '2026-11', ResourceReference::of('member', (string) intdiv($params['members'], 2))) ?? throw new LogicException('The member has no score.');
        $this->division = new Circle(...array_map(static fn (int $index): ResourceReference => ResourceReference::of('member', (string) ($index * 31)), range(0, 29)));
    }

    /**
     * @return Generator<string, array{store: string, members: int}>
     */
    public function provideBoards(): Generator
    {
        foreach (array_merge(['memory'], \is_string(getenv('PHIL_TEST_VALKEY_URL')) && '' !== getenv('PHIL_TEST_VALKEY_URL') ? ['valkey'] : []) as $store) {
            foreach ([1_000, 10_000] as $members) {
                yield $store.' '.$members => ['store' => $store, 'members' => $members];
            }
        }
    }

    /**
     * @ParamProviders("provideBoards")
     *
     * @Revs(20)
     */
    public function benchTopTen(): void
    {
        $this->store->top('games', '2026-11', 10, $this->ranking);
    }

    /**
     * @ParamProviders("provideBoards")
     *
     * @Revs(20)
     */
    public function benchRankOfAMember(): void
    {
        $this->store->ahead($this->member, $this->ranking);
    }

    /**
     * @ParamProviders("provideBoards")
     *
     * @Revs(20)
     */
    public function benchParticipants(): void
    {
        $this->store->participants('games', '2026-11');
    }

    /**
     * @ParamProviders("provideBoards")
     *
     * @Revs(20)
     */
    public function benchDivisionOfThirty(): void
    {
        $this->store->top('games', '2026-11', 30, $this->ranking, $this->division);
    }
}
