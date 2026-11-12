<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit\Store;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Contract\GoalStore;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Goal;

/**
 * The contract any goal store passes: extend it with the store to test.
 */
abstract class GoalStoreTestCase extends TestCase
{
    abstract protected function store(): GoalStore;

    public function testAGoalIsKeptReplacedAndRemoved(): void
    {
        $store = $this->store();
        $author = ResourceReference::of('member', '1');

        self::assertNull($store->find('novel'));

        $store->save($this->goal('novel', $author, 1000));
        $store->save($this->goal('novel', $author, 2000));

        self::assertSame(2000, $store->find('novel')?->target);

        $store->remove('novel');
        $store->remove('novel');

        self::assertNull($store->find('novel'));
    }

    public function testTheGoalsOfAnAuthorComeByIdentifier(): void
    {
        $store = $this->store();
        $author = ResourceReference::of('member', '1');

        foreach (['b', 'a', '10', '9'] as $id) {
            $store->save($this->goal($id, $author, 1));
        }

        $store->save($this->goal('other', ResourceReference::of('member', '2'), 1));

        self::assertSame(['10', '9', 'a', 'b'], array_map(static fn (Goal $goal): string => $goal->id, $store->goalsOf($author)));
        self::assertSame([], $store->goalsOf(ResourceReference::of('member', '3')));
    }

    private function goal(string $id, ResourceReference $author, int $target): Goal
    {
        return new Goal($id, $author, $target, Day::fromString('2026-11-01'), Day::fromString('2026-11-30'));
    }
}
