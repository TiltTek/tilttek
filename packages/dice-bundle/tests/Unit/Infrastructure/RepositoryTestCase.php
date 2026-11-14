<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;

/**
 * What every journal does: keep a roll, find it again, alone or many at once, list an owner's rolls and a context's the
 * latest first, and erase an owner's.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testARollIsKeptAndFoundAgain(): void
    {
        $repository = $this->repository();
        $roll = $this->roll(1, 'member:1', '2026-11-07 03:00:00');
        $repository->save($roll);
        $this->clear();

        $found = $repository->get($roll->id());
        self::assertNotNull($found);
        self::assertSame([$roll->id(), $roll->expression(), $roll->seed()->hex(), $roll->result(), 'member:1', $roll->rolledAt()->getTimestamp()], [$found->id(), $found->expression(), $found->seed()->hex(), $found->result(), $found->owner()?->toString(), $found->rolledAt()->getTimestamp()]);
        self::assertNull($repository->get('00000000000000000000000000000000'));
    }

    public function testAnOwnersRollsAreListedTheLatestFirstAndErased(): void
    {
        $repository = $this->repository();
        $member = ResourceReference::of('member', '1');
        $repository->save($this->roll(1, 'member:1', '2026-11-07 03:00:00'));
        $repository->save($this->roll(30, 'member:1', '2026-11-07 04:00:00'));
        $repository->save($this->roll(13, 'member:1', '2026-11-07 04:00:00'));
        $repository->save($this->roll(60, 'member:2', '2026-11-07 05:00:00'));
        $repository->save($this->roll(90, null, '2026-11-07 05:00:00'));
        $this->clear();

        self::assertSame(['0d0000000e0000000f00000010000000', '1e0000001f0000002000000021000000', '01000000020000000300000004000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofOwner($member, 50)));
        self::assertSame(['0d0000000e0000000f00000010000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofOwner($member, 1)));
        self::assertSame(3, $repository->eraseOwner($member));
        $this->clear();
        self::assertSame([], $repository->ofOwner($member, 50));
        self::assertSame(0, $repository->eraseOwner($member));
        self::assertCount(1, $repository->ofOwner(ResourceReference::of('member', '2'), 50));
        self::assertNotNull($repository->get('5a0000005b0000005c0000005d000000'));
    }

    public function testRollsAreFoundManyAtOnceInTheOrderAsked(): void
    {
        $repository = $this->repository();
        $first = $this->roll(1, 'member:1', '2026-11-07 03:00:00');
        $second = $this->roll(30, null, '2026-11-07 04:00:00', 'table:7');
        $repository->save($first);
        $repository->save($second);
        $this->clear();

        self::assertSame([$second->id(), $first->id()], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->getMany([$second->id(), '00000000000000000000000000000000', $first->id(), $second->id()])));
        self::assertSame('table:7', $repository->getMany([$second->id()])[0]->context()?->toString());
        self::assertNull($repository->getMany([$first->id()])[0]->context());
        self::assertSame([], $repository->getMany([]));
        self::assertSame([], $repository->getMany(['00000000000000000000000000000000']));
    }

    public function testAContextsRollsAreListedTheLatestFirst(): void
    {
        $repository = $this->repository();
        $table = ResourceReference::of('table', '7');
        $repository->save($this->roll(1, 'member:1', '2026-11-07 03:00:00', 'table:7'));
        $repository->save($this->roll(30, 'member:2', '2026-11-07 04:00:00', 'table:7'));
        $repository->save($this->roll(13, null, '2026-11-07 04:00:00', 'table:7'));
        $repository->save($this->roll(60, 'member:1', '2026-11-07 05:00:00', 'table:8'));
        $repository->save($this->roll(90, 'member:1', '2026-11-07 06:00:00'));
        $this->clear();

        self::assertSame(['0d0000000e0000000f00000010000000', '1e0000001f0000002000000021000000', '01000000020000000300000004000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofContext($table, 50)));
        self::assertSame(['0d0000000e0000000f00000010000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofContext($table, 1)));
        self::assertSame([], $repository->ofContext(ResourceReference::of('table', '9'), 50));
        self::assertSame(3, $repository->eraseOwner(ResourceReference::of('member', '1')));
        $this->clear();
        self::assertCount(2, $repository->ofContext($table, 50));
        self::assertSame([], $repository->ofContext(ResourceReference::of('table', '8'), 50));
    }

    abstract protected function repository(): RecordedRollRepository;

    protected function clear(): void
    {
    }

    private function roll(int $first, ?string $owner, string $at, ?string $context = null): RecordedRoll
    {
        $roll = new Replayer()->roll(new DiceParser()->parse('3d6+1'), ScriptedEngine::randomizer(...range($first, $first + 11)));

        return RecordedRoll::record($roll, null === $owner ? null : ResourceReference::fromString($owner), new DateTimeImmutable($at.' UTC'), null === $context ? null : ResourceReference::fromString($context));
    }
}
