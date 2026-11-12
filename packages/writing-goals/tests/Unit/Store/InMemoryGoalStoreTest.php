<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\WritingGoals\Contract\GoalStore;
use Tilttek\WritingGoals\Store\InMemoryGoalStore;

#[CoversClass(InMemoryGoalStore::class)]
final class InMemoryGoalStoreTest extends GoalStoreTestCase
{
    #[Override]
    protected function store(): GoalStore
    {
        return new InMemoryGoalStore();
    }
}
