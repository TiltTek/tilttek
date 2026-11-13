<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;

#[CoversClass(InMemoryTableStore::class)]
#[CoversClass(InMemoryParticipationStore::class)]
#[CoversClass(InMemorySignalStore::class)]
#[CoversClass(InMemoryFeedbackStore::class)]
final class InMemoryStoresTest extends StoresTestCase
{
    #[Override]
    protected function tables(): TableStore
    {
        return new InMemoryTableStore();
    }

    #[Override]
    protected function participations(): ParticipationStore
    {
        return new InMemoryParticipationStore();
    }

    #[Override]
    protected function signals(): SignalStore
    {
        return new InMemorySignalStore();
    }

    #[Override]
    protected function feedback(): FeedbackStore
    {
        return new InMemoryFeedbackStore();
    }
}
