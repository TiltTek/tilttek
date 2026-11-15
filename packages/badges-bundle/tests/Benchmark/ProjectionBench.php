<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Badges\Award;
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalAwardStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProgressStore;
use Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Database;

/**
 * What the projection of `tilttek/badges` costs a recorded activity on the Doctrine stores, over SQLite in memory, and
 * the share of `deliver()` it runs first — two statements, the withdrawn and the undelivered awards whose notice is
 * due, with none due —: one activity that reaches no target among twenty counted badges, the delivery alone, and the
 * activity of a verb no badge counts, on a table holding 1 000 and 10 000 awards across a hundred members.
 *
 * @BeforeMethods("setUp")
 */
final class ProjectionBench
{
    private const int BADGES = 20;

    private const int MEMBERS = 100;

    private BadgeProjection $projection;

    private readonly ResourceReference $member;

    private readonly DateTimeImmutable $at;

    private int $activities = 0;

    public function __construct()
    {
        $connection = Database::connection();
        $progress = new DbalProgressStore($connection);
        $this->projection = new BadgeProjection(new Evaluator(new Catalog(), $progress), $progress, new DbalAwardStore($connection));
        $this->member = ResourceReference::of('member', '0');
        $this->at = new DateTimeImmutable('2026-11-01 00:00 UTC');
    }

    /**
     * @param array{awards: int} $params
     */
    public function setUp(array $params): void
    {
        $badges = ['held' => ['repeatable' => true, 'criterion' => ['manual' => true]]];

        for ($index = 0; $index < self::BADGES; ++$index) {
            $badges['count-'.$index] = ['criterion' => ['count' => ['verbs' => ['game.played', 'news.n'.$index], 'times' => 1_000_000]]];
        }

        $connection = Database::connection();
        $progress = new DbalProgressStore($connection);
        $awards = new DbalAwardStore($connection);
        $connection->beginTransaction();

        // Awards made by hand: their notice is never due, as after a delivery.
        for ($index = 0; $index < $params['awards']; ++$index) {
            $awards->add(new Award('held', ResourceReference::of('member', (string) ($index % self::MEMBERS)), intdiv($index, self::MEMBERS) + 1, $this->at, 'bench', true));
        }

        $connection->commit();
        $this->projection = new BadgeProjection(new Evaluator(Catalog::fromArray($badges), $progress), $progress, $awards);
    }

    /**
     * @return Generator<string, array{awards: int}>
     */
    public function provideStores(): Generator
    {
        foreach ([1_000, 10_000] as $awards) {
            yield $awards.' awards' => ['awards' => $awards];
        }
    }

    /**
     * @ParamProviders("provideStores")
     *
     * @Revs(200)
     */
    public function benchApplyCounted(): void
    {
        $this->projection->apply($this->entry('game.played'));
    }

    /**
     * @ParamProviders("provideStores")
     *
     * @Revs(200)
     */
    public function benchApplyUncounted(): void
    {
        $this->projection->apply($this->entry('site.visited'));
    }

    /**
     * @ParamProviders("provideStores")
     *
     * @Revs(200)
     */
    public function benchDeliver(): void
    {
        $this->projection->deliver($this->member);
    }

    private function entry(string $verb): Entry
    {
        ++$this->activities;

        return new Entry($this->activities, EntryKind::Recorded, Activity::of('a'.$this->activities, 'member:0', $verb, $this->at), $this->at);
    }
}
