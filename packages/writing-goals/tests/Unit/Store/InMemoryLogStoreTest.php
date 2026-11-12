<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\WritingGoals\Contract\LogStore;
use Tilttek\WritingGoals\Store\InMemoryLogStore;

#[CoversClass(InMemoryLogStore::class)]
final class InMemoryLogStoreTest extends LogStoreTestCase
{
    #[Override]
    protected function store(): LogStore
    {
        return new InMemoryLogStore();
    }
}
