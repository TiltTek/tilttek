<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Benchmark;

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
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryProgressStore;

/**
 * What the projection costs a recorded activity, and the share of `deliver()` it runs first — the two reads of the
 * awards whose notice is due, withdrawn and undelivered, with none due —, over the stores in memory: one activity that
 * reaches no target among twenty counted badges, the delivery alone, and the activity of a verb no badge counts, on a
 * store holding 1 000 and 10 000 awards across a hundred members.
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
        $progress = new InMemoryProgressStore();
        $this->projection = new BadgeProjection(new Evaluator(new Catalog(), $progress), $progress, new InMemoryAwardStore());
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

        $progress = new InMemoryProgressStore();
        $awards = new InMemoryAwardStore();

        // Awards made by hand: their notice is never due, as after a delivery.
        for ($index = 0; $index < $params['awards']; ++$index) {
            $awards->add(new Award('held', ResourceReference::of('member', (string) ($index % self::MEMBERS)), intdiv($index, self::MEMBERS) + 1, $this->at, 'bench', true));
        }

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
